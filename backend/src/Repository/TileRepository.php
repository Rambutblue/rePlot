<?php

declare(strict_types=1);

namespace App\Repository;

use App\Tile\Tile;
use Doctrine\DBAL\Connection;

final readonly class TileRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function render(Tile $tile): string
    {
        $layers = [];
        $params = ['z' => $tile->z, 'x' => $tile->x, 'y' => $tile->y];

        if ($tile->hasAreas()) {
            $layers[] = <<<'SQL'
                (SELECT ST_AsMVT(l.*, 'areas', 4096, 'geom', 'code') FROM (
                    SELECT a.code, a.name, ST_AsMVTGeom(a.outline, b.geom, 4096, 64, true) AS geom
                    FROM cadastral_area a, bounds b
                    WHERE a.outline && b.query_geom
                ) l WHERE l.geom IS NOT NULL)
                SQL;
        }

        if ($tile->hasParcels()) {
            $layers[] = <<<'SQL'
                (SELECT ST_AsMVT(l.*, 'parcels', 4096, 'geom', 'id') FROM (
                    SELECT p.id, p.label, p.land_type AS "landType", ST_AsMVTGeom(p.geom, b.geom, 4096, 64, true) AS geom
                    FROM parcel p, bounds b
                    WHERE p.geom && b.query_geom AND p.area_m2 >= :minArea
                ) l WHERE l.geom IS NOT NULL)
                SQL;
            $params['minArea'] = $tile->parcelMinAreaM2();
        }

        $sql = sprintf(
            <<<'SQL'
                WITH bounds AS (
                    SELECT ST_TileEnvelope(:z, :x, :y) AS geom,
                           ST_TileEnvelope(:z, :x, :y, margin => 64.0 / 4096) AS query_geom
                )
                SELECT %s FROM bounds
                SQL,
            implode(' || ', array_map(static fn (string $layer): string => sprintf('COALESCE(%s, \'\'::bytea)', $layer), $layers)),
        );

        $result = $this->connection->fetchOne($sql, $params);

        return \is_resource($result) ? (string) stream_get_contents($result) : (string) $result;
    }
}
