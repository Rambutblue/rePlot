<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cadastre;

use App\Cadastre\LandType;
use App\Cadastre\LandUse;
use PHPUnit\Framework\TestCase;

final class LandCodesTest extends TestCase
{
    public function testLandTypeCodesMapToCzechLabels(): void
    {
        self::assertSame('orná půda', LandType::from('ArableGround')->label());
        self::assertSame('trvalý travní porost', LandType::from('Grassland')->label());
        self::assertSame('zastavěná plocha a nádvoří', LandType::from('BuiltUpArea')->label());
        self::assertCount(10, LandType::cases());
    }

    public function testLandUseCodesMapToCzechLabels(): void
    {
        self::assertSame('ostatní komunikace', LandUse::from('OtherRoads')->label());
        self::assertSame('jiná plocha', LandUse::from('OtherArea')->label());
        self::assertCount(29, LandUse::cases());
    }

    public function testUnknownCodesAreNotMapped(): void
    {
        self::assertNull(LandType::tryFrom('Moon'));
        self::assertNull(LandUse::tryFrom('arableground'));
    }
}
