<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final readonly class ParcelRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{
     *     id: int, label: string, reference: ?string, area_m2: ?int, land_type: ?string, land_use: ?string,
     *     area_code: int, area_name: string, municipality_code: ?int, municipality_name: ?string,
     *     lng: float, lat: float, west: float, south: float, east: float, north: float
     * }|null
     */
    public function find(int $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT p.id, p.label, p.reference, p.area_m2, p.land_type, p.land_use,
                       a.code AS area_code, a.name AS area_name, a.municipality_code, a.municipality_name,
                       ST_X(c.geom) AS lng, ST_Y(c.geom) AS lat,
                       ST_XMin(e.box) AS west, ST_YMin(e.box) AS south, ST_XMax(e.box) AS east, ST_YMax(e.box) AS north
                FROM parcel p
                JOIN cadastral_area a ON a.code = p.area_code
                CROSS JOIN LATERAL (SELECT ST_Transform(ST_PointOnSurface(p.geom), 4326) AS geom) c
                CROSS JOIN LATERAL (SELECT ST_Transform(ST_Envelope(p.geom), 4326)::box2d AS box) e
                WHERE p.id = :id
                SQL,
            ['id' => $id],
        );

        /** @var array{id: int, label: string, reference: ?string, area_m2: ?int, land_type: ?string, land_use: ?string, area_code: int, area_name: string, municipality_code: ?int, municipality_name: ?string, lng: float, lat: float, west: float, south: float, east: float, north: float}|false $row */
        return $row === false ? null : $row;
    }
}
