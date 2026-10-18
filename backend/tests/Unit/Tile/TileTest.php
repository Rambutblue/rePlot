<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tile;

use App\Tile\Tile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TileTest extends TestCase
{
    public function testLayersOverlapAroundTheSwitchZoom(): void
    {
        self::assertTrue((new Tile(11, 0, 0))->hasAreas());
        self::assertFalse((new Tile(11, 0, 0))->hasParcels());

        self::assertTrue((new Tile(12, 0, 0))->hasAreas());
        self::assertTrue((new Tile(13, 0, 0))->hasParcels());

        self::assertFalse((new Tile(14, 0, 0))->hasAreas());
        self::assertTrue((new Tile(14, 0, 0))->hasParcels());
    }

    public function testSmallParcelsAreDroppedOnlyAtLowZoom(): void
    {
        self::assertGreaterThan((new Tile(13, 0, 0))->parcelMinAreaM2(), (new Tile(12, 0, 0))->parcelMinAreaM2());
        self::assertGreaterThan(0, (new Tile(13, 0, 0))->parcelMinAreaM2());
        self::assertSame(0, (new Tile(14, 0, 0))->parcelMinAreaM2());
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'negative zoom' => [-1, 0, 0];
        yield 'zoom too deep' => [23, 0, 0];
        yield 'x outside zoom' => [3, 8, 0];
        yield 'y outside zoom' => [3, 0, 8];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidCoordinatesAreRejected(int $z, int $x, int $y): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Tile($z, $x, $y);
    }
}
