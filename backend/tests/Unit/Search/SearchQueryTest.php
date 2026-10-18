<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search;

use App\Search\SearchQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchQueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string, ?string}>
     */
    public static function queryProvider(): iterable
    {
        yield 'parcel with area' => ['1854/36 Jičín', '1854/36', 'Jičín'];
        yield 'area before parcel' => ['Jičín, 1854/36', '1854/36', 'Jičín'];
        yield 'building parcel' => ['st. 123', 'st. 123', null];
        yield 'building parcel without dot or space' => ['St.123 Vinice', 'st. 123', 'Vinice'];
        yield 'building parcel without dot' => ['st 5/2', 'st. 5/2', null];
        yield 'bare number' => ['1854', '1854', null];
        yield 'area only' => ['Vinice v Podkrkonoší', null, 'Vinice v Podkrkonoší'];
        yield 'extra whitespace' => ["  1854/36   Jičín  ", '1854/36', 'Jičín'];
        yield 'number inside a word is not a parcel' => ['Jičín2', null, 'Jičín2'];
    }

    #[DataProvider('queryProvider')]
    public function testParse(string $input, ?string $label, ?string $area): void
    {
        $query = SearchQuery::parse($input);

        self::assertNotNull($query);
        self::assertSame($label, $query->parcelLabel);
        self::assertSame($area, $query->areaName);
    }

    public function testTooShortInputIsIgnored(): void
    {
        self::assertNull(SearchQuery::parse(''));
        self::assertNull(SearchQuery::parse(' j '));
        self::assertNotNull(SearchQuery::parse('5'));
    }

    public function testOnlyLabelsWithoutSubdivisionArePrefixSearches(): void
    {
        self::assertTrue(SearchQuery::parse('1854')?->isPrefix());
        self::assertFalse(SearchQuery::parse('1854/36')?->isPrefix());
        self::assertFalse(SearchQuery::parse('Jičín')?->isPrefix());
    }
}
