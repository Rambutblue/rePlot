<?php

declare(strict_types=1);

namespace App\Import;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

final readonly class Ogr2OgrLoader
{
    private PostgresConnection $connection;

    public function __construct(
        #[Autowire(env: 'DATABASE_URL')] string $databaseUrl,
        #[Autowire(env: 'OGR2OGR_BIN')] private string $binary,
    ) {
        $this->connection = PostgresConnection::fromUrl($databaseUrl);
    }

    public function load(string $gmlPath, string $layer, string $table): void
    {
        $process = new Process(
            [
                $this->binary,
                '-f', 'PostgreSQL',
                'PG:'.$this->connection->conninfo(),
                $gmlPath,
                $layer,
                '-oo', 'WRITE_GFS=NO',
                '--config', 'GML_ATTRIBUTES_TO_OGR_FIELDS', 'YES',
                '-nln', 'staging.'.$table,
                '-overwrite',
                '-lco', 'GEOMETRY_NAME=geom',
                '-lco', 'FID=ogc_fid',
                '-lco', 'SPATIAL_INDEX=NONE',
                '-lco', 'UNLOGGED=ON',
                '-a_srs', 'EPSG:5514',
                '-nlt', 'MULTIPOLYGON',
            ],
            env: ['PGPASSWORD' => $this->connection->password ?? ''],
            timeout: 3600,
        );
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'ogr2ogr failed for layer %s (exit %d): %s',
                $layer,
                $process->getExitCode(),
                mb_substr(trim($process->getErrorOutput()), -2000),
            ));
        }
    }
}
