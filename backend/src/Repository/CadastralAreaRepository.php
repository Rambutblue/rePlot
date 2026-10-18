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

    /**
     * @return list<array{
     *     code: int, name: string, municipality_code: ?int, municipality_name: ?string, status: string, parcel_count: int,
     *     west: ?float, south: ?float, east: ?float, north: ?float
     * }>
     */
    public function findAll(): array
    {
        /** @var list<array{code: int, name: string, municipality_code: ?int, municipality_name: ?string, status: string, parcel_count: int, west: ?float, south: ?float, east: ?float, north: ?float}> */
        return $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT a.code, a.name, a.municipality_code, a.municipality_name, a.status, a.parcel_count,
                       ST_XMin(e.box) AS west, ST_YMin(e.box) AS south, ST_XMax(e.box) AS east, ST_YMax(e.box) AS north
                FROM cadastral_area a
                CROSS JOIN LATERAL (SELECT ST_Transform(ST_Envelope(a.outline), 4326)::box2d AS box) e
                ORDER BY a.name, a.code
                SQL,
        );
    }

    /**
     * @return array{total: int, done: int, failed: int, pending: int, current_code: ?int, current_name: ?string}
     */
    public function importProgress(): array
    {
        /** @var array{total: int, done: int, failed: int, pending: int, current_code: ?int, current_name: ?string} */
        return $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT count(*) AS total,
                       count(*) FILTER (WHERE status = 'done') AS done,
                       count(*) FILTER (WHERE status = 'failed') AS failed,
                       count(*) FILTER (WHERE status IN ('pending', 'importing')) AS pending,
                       (SELECT code FROM cadastral_area WHERE status = 'importing' LIMIT 1) AS current_code,
                       (SELECT name FROM cadastral_area WHERE status = 'importing' LIMIT 1) AS current_name
                FROM cadastral_area
                SQL,
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
