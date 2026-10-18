<?php

declare(strict_types=1);

namespace App\Repository;

use App\Search\SearchQuery;
use Doctrine\DBAL\Connection;

final readonly class SearchRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<array{
     *     id: int, label: string, area_code: int, area_name: string,
     *     lng: float, lat: float, west: float, south: float, east: float, north: float
     * }>
     */
    public function parcels(SearchQuery $query, int $limit): array
    {
        if ($query->parcelLabel === null) {
            return [];
        }

        $params = ['label' => $query->parcelLabel, 'limit' => $limit];
        $conditions = ['p.label = :label'];
        if ($query->isPrefix()) {
            $conditions[0] = '(p.label = :label OR p.label LIKE :labelPrefix)';
            $params['labelPrefix'] = $query->parcelLabel.'/%';
        }

        $areaOrder = '';
        if ($query->areaName !== null) {
            $conditions[] = "immutable_unaccent(lower(a.name)) LIKE '%' || immutable_unaccent(lower(:area)) || '%'";
            $areaOrder = "immutable_unaccent(lower(a.name)) LIKE immutable_unaccent(lower(:area)) || '%' DESC,";
            $params['area'] = self::escapeLike($query->areaName);
        }

        /** @var list<array{id: int, label: string, area_code: int, area_name: string, lng: float, lat: float, west: float, south: float, east: float, north: float}> */
        return $this->connection->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                    SELECT p.id, p.label, a.code AS area_code, a.name AS area_name,
                           ST_X(c.geom) AS lng, ST_Y(c.geom) AS lat,
                           ST_XMin(e.box) AS west, ST_YMin(e.box) AS south, ST_XMax(e.box) AS east, ST_YMax(e.box) AS north
                    FROM parcel p
                    JOIN cadastral_area a ON a.code = p.area_code
                    CROSS JOIN LATERAL (SELECT ST_Transform(ST_PointOnSurface(p.geom), 4326) AS geom) c
                    CROSS JOIN LATERAL (SELECT ST_Transform(ST_Envelope(p.geom), 4326)::box2d AS box) e
                    WHERE %s
                    ORDER BY p.label = :label DESC, %s a.name, length(p.label), p.label
                    LIMIT :limit
                    SQL,
                implode(' AND ', $conditions),
                $areaOrder,
            ),
            $params,
        );
    }

    /**
     * @return list<array{
     *     code: int, name: string, municipality_name: ?string,
     *     lng: float, lat: float, west: float, south: float, east: float, north: float
     * }>
     */
    public function areas(string $name, int $limit): array
    {
        /** @var list<array{code: int, name: string, municipality_name: ?string, lng: float, lat: float, west: float, south: float, east: float, north: float}> */
        return $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT a.code, a.name, a.municipality_name,
                       ST_X(c.geom) AS lng, ST_Y(c.geom) AS lat,
                       ST_XMin(e.box) AS west, ST_YMin(e.box) AS south, ST_XMax(e.box) AS east, ST_YMax(e.box) AS north
                FROM cadastral_area a
                CROSS JOIN LATERAL (SELECT immutable_unaccent(lower(:name)) AS q) n
                CROSS JOIN LATERAL (SELECT ST_Transform(ST_PointOnSurface(a.outline), 4326) AS geom) c
                CROSS JOIN LATERAL (SELECT ST_Transform(ST_Envelope(a.outline), 4326)::box2d AS box) e
                WHERE a.outline IS NOT NULL
                  AND (immutable_unaccent(lower(a.name)) LIKE '%' || n.q || '%'
                       OR immutable_unaccent(lower(a.municipality_name)) LIKE '%' || n.q || '%')
                ORDER BY immutable_unaccent(lower(a.name)) LIKE n.q || '%' DESC,
                         immutable_unaccent(lower(a.name)) LIKE '%' || n.q || '%' DESC,
                         similarity(immutable_unaccent(lower(a.name)), n.q) DESC,
                         a.name
                LIMIT :limit
                SQL,
            ['name' => self::escapeLike($name), 'limit' => $limit],
        );
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
