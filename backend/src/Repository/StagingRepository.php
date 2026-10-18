<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final readonly class StagingRepository
{
    public const string PARCEL_TABLE = 'cpx_parcel';
    public const string ZONING_TABLE = 'cpx_zoning';

    private const array OPTIONAL_PARCEL_COLUMNS = [
        'nationalcadastralreference' => 'text',
        'areavalue' => 'integer',
        'landtype_href' => 'text',
        'landuse_href' => 'text',
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function replaceArea(int $code): int
    {
        $zoningCode = $this->connection->fetchOne(
            sprintf('SELECT nationalcadastalzoningreference FROM staging.%s', self::ZONING_TABLE),
        );
        if ((int) $zoningCode !== $code) {
            throw new \RuntimeException(sprintf('Staging zoning is %s, expected %d.', var_export($zoningCode, true), $code));
        }

        $staged = (int) $this->connection->fetchOne(sprintf('SELECT count(*) FROM staging.%s', self::PARCEL_TABLE));
        if ($staged === 0) {
            throw new \RuntimeException('No parcels were loaded into staging.');
        }

        $columns = $this->parcelColumns();

        return $this->connection->transactional(function (Connection $connection) use ($code, $columns, $staged): int {
            $connection->executeStatement('DELETE FROM parcel WHERE area_code = :code', ['code' => $code]);

            $inserted = (int) $connection->executeStatement(
                sprintf(
                    <<<'SQL'
                        INSERT INTO parcel (id, area_code, label, reference, area_m2, land_type, land_use, geom)
                        SELECT
                            substring(s.localid FROM '^CPX\.(\d+)$')::bigint,
                            :code,
                            s.label,
                            %s,
                            %s,
                            substring(%s FROM '/LandTypeValue/(\w+)$'),
                            substring(%s FROM '/LandUseValue/(\w+)$'),
                            CASE WHEN ST_IsValid(t.geom) THEN t.geom
                                 ELSE ST_Multi(ST_CollectionExtract(ST_MakeValid(t.geom), 3)) END
                        FROM staging.%s s
                        CROSS JOIN LATERAL (SELECT ST_Transform(s.geom, 3857) AS geom) t
                        WHERE s.geom IS NOT NULL
                        SQL,
                    $columns['nationalcadastralreference'],
                    $columns['areavalue'],
                    $columns['landtype_href'],
                    $columns['landuse_href'],
                    self::PARCEL_TABLE,
                ),
                ['code' => $code],
            );
            if ($inserted !== $staged) {
                throw new \RuntimeException(sprintf('Inserted %d of %d staged parcels.', $inserted, $staged));
            }

            $connection->executeStatement(
                sprintf(
                    <<<'SQL'
                        UPDATE cadastral_area SET
                            status = 'done',
                            parcel_count = :count,
                            outline = (SELECT ST_Multi(ST_Transform(geom, 3857)) FROM staging.%s LIMIT 1),
                            error = NULL,
                            imported_at = now()
                        WHERE code = :code
                        SQL,
                    self::ZONING_TABLE,
                ),
                ['code' => $code, 'count' => $inserted],
            );
            $connection->executeStatement('UPDATE app_state SET data_version = data_version + 1');

            return $inserted;
        });
    }

    public function dropTables(): void
    {
        $this->connection->executeStatement(
            sprintf('DROP TABLE IF EXISTS staging.%s, staging.%s', self::PARCEL_TABLE, self::ZONING_TABLE),
        );
    }

    /**
     * @return array<string, string>
     */
    private function parcelColumns(): array
    {
        $existing = $this->connection->fetchFirstColumn(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = 'staging' AND table_name = :table",
            ['table' => self::PARCEL_TABLE],
        );

        $columns = [];
        foreach (self::OPTIONAL_PARCEL_COLUMNS as $column => $type) {
            $columns[$column] = \in_array($column, $existing, true)
                ? sprintf('s.%s::%s', $column, $type)
                : sprintf('NULL::%s', $type);
        }

        return $columns;
    }
}
