<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\AreaImporter;
use App\Import\CadastralAreaInfo;
use App\Import\ImportScope;
use App\Import\RuianClient;
use App\Repository\CadastralAreaRepository;
use App\Repository\StagingRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('app:import', 'Import cadastral parcels from ČÚZK for the areas configured in .env')]
final readonly class ImportCommand
{
    public function __construct(
        private RuianClient $ruian,
        private CadastralAreaRepository $areas,
        private StagingRepository $staging,
        private AreaImporter $importer,
        private Connection $connection,
        #[Autowire(env: 'IMPORT_CADASTRAL_AREAS')] private string $cadastralAreas,
        #[Autowire(env: 'IMPORT_DISTRICT')] private string $district,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if ($this->connection->fetchOne("SELECT pg_try_advisory_lock(hashtext('app:import'))") !== true) {
            $io->warning('Another import is already running.');

            return Command::SUCCESS;
        }

        $scope = ImportScope::fromEnv($this->cadastralAreas, $this->district);
        $io->text(sprintf('Resolving %s', $scope->describe()));
        $resolved = $this->ruian->resolve($scope);
        $this->areas->upsertPending($resolved);

        $pending = $this->areas->findNotDone(array_map(static fn (CadastralAreaInfo $area): int => $area->code, $resolved));
        $io->text(sprintf('%d cadastral areas in scope, %d to import', \count($resolved), \count($pending)));

        $failed = 0;
        foreach ($pending as $i => $area) {
            $started = microtime(true);
            try {
                $count = $this->importer->import($area['code']);
                $io->text(sprintf(
                    '[%d/%d] %s (%d): %d parcels in %.1f s',
                    $i + 1,
                    \count($pending),
                    $area['name'],
                    $area['code'],
                    $count,
                    microtime(true) - $started,
                ));
            } catch (\Throwable $e) {
                ++$failed;
                $this->areas->markFailed($area['code'], $e->getMessage());
                $io->error(sprintf('%s (%d): %s', $area['name'], $area['code'], $e->getMessage()));
            }
        }

        $this->staging->dropTables();
        $this->connection->executeStatement('ANALYZE parcel');
        $this->connection->executeStatement('ANALYZE cadastral_area');

        if ($failed > 0) {
            $io->warning(sprintf('%d cadastral areas failed, run the import again to retry them.', $failed));

            return Command::FAILURE;
        }

        $io->success('Import finished.');

        return Command::SUCCESS;
    }
}
