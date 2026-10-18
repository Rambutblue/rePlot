<?php

declare(strict_types=1);

namespace App\Tile;

final readonly class Tile
{
    public const int MAX_ZOOM = 22;
    public const int AREAS_MAX_ZOOM = 13;
    public const int PARCELS_MIN_ZOOM = 12;

    private const array PARCEL_MIN_AREA_M2 = [12 => 2500, 13 => 600];

    public function __construct(
        public int $z,
        public int $x,
        public int $y,
    ) {
        if ($z < 0 || $z > self::MAX_ZOOM) {
            throw new \InvalidArgumentException(sprintf('Zoom %d is out of range.', $z));
        }
        $size = 2 ** $z;
        if ($x < 0 || $x >= $size || $y < 0 || $y >= $size) {
            throw new \InvalidArgumentException(sprintf('Tile %d/%d/%d does not exist.', $z, $x, $y));
        }
    }

    public function hasAreas(): bool
    {
        return $this->z <= self::AREAS_MAX_ZOOM;
    }

    public function hasParcels(): bool
    {
        return $this->z >= self::PARCELS_MIN_ZOOM;
    }

    public function parcelMinAreaM2(): int
    {
        return self::PARCEL_MIN_AREA_M2[$this->z] ?? 0;
    }
}
