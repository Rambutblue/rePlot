<?php

declare(strict_types=1);

namespace App\Repository;

use App\Import\CadastralAreaInfo;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class CadastralAreaRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param list<CadastralAreaInfo> $areas
     */
    public function upsertPending(array $areas): void
    {
        $this->connection->transactional(function (Connection $connection) use ($areas): void {
            foreach ($areas as $area) {
                $connection->executeStatement(
                    <<<'SQL'
                        INSERT INTO cadastral_area (code, name, municipality_code, municipality_name)
                        VALUES (:code, :name, :municipalityCode, :municipalityName)
                        ON CONFLICT (code) DO UPDATE SET
                            name = excluded.name,
                            municipality_code = excluded.municipality_code,
                            municipality_name = excluded.municipality_name
                        SQL,
                    [
                        'code' => $area->code,
                        'name' => $area->name,
                        'municipalityCode' => $area->municipalityCode,
                        'municipalityName' => $area->municipalityName,
                    ],
                );
            }
        });
    }

    /**
     * @param list<int> $codes
     *
     * @return list<array{code: int, name: string}>
     */
    public function findNotDone(array $codes): array
    {
        /** @var list<array{code: int, name: string}> */
        return $this->connection->fetchAllAssociative(
            "SELECT code, name FROM cadastral_area WHERE code IN (:codes) AND status <> 'done' ORDER BY code",
            ['codes' => $codes],
            ['codes' => ArrayParameterType::INTEGER],
        );
    }

    public function markImporting(int $code): void
    {
        $this->connection->executeStatement(
            "UPDATE cadastral_area SET status = 'importing', error = NULL WHERE code = :code",
            ['code' => $code],
        );
    }

    public function markFailed(int $code, string $error): void
    {
        $this->connection->executeStatement(
            "UPDATE cadastral_area SET status = 'failed', error = :error WHERE code = :code",
            ['code' => $code, 'error' => mb_substr($error, 0, 4000)],
        );
    }
}
