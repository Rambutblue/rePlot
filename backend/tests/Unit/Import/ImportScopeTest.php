<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\ImportScope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportScopeTest extends TestCase
{
    public function testDistrictIsUsedWhenNoAreasAreListed(): void
    {
        $scope = ImportScope::fromEnv('', '3604');

        self::assertSame(3604, $scope->districtCode);
        self::assertSame([], $scope->cadastralAreaCodes);
    }

    public function testAreaListOverridesDistrict(): void
    {
        $scope = ImportScope::fromEnv('770141,659541', '3604');

        self::assertNull($scope->districtCode);
        self::assertSame([770141, 659541], $scope->cadastralAreaCodes);
    }

    public function testAreaListAcceptsMixedSeparatorsAndDropsDuplicates(): void
    {
        $scope = ImportScope::fromEnv(" 770141; 659541\n770141 ,", '');

        self::assertSame([770141, 659541], $scope->cadastralAreaCodes);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'nothing set' => ['', ''];
        yield 'blank values' => ['  ', ' '];
        yield 'non-numeric area' => ['770141,abc', '3604'];
        yield 'non-numeric district' => ['', 'CZ0522'];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidValuesAreRejected(string $areas, string $district): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ImportScope::fromEnv($areas, $district);
    }
}
