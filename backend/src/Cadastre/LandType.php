<?php

declare(strict_types=1);

namespace App\Cadastre;

enum LandType: string
{
    case ArableGround = 'ArableGround';
    case Hopgarden = 'Hopgarden';
    case Vineyard = 'Vineyard';
    case Garden = 'Garden';
    case Orchard = 'Orchard';
    case Grassland = 'Grassland';
    case Forest = 'Forest';
    case WaterArea = 'WaterArea';
    case BuiltUpArea = 'BuiltUpArea';
    case OtherArea = 'OtherArea';

    public function label(): string
    {
        return match ($this) {
            self::ArableGround => 'orná půda',
            self::Hopgarden => 'chmelnice',
            self::Vineyard => 'vinice',
            self::Garden => 'zahrada',
            self::Orchard => 'ovocný sad',
            self::Grassland => 'trvalý travní porost',
            self::Forest => 'lesní pozemek',
            self::WaterArea => 'vodní plocha',
            self::BuiltUpArea => 'zastavěná plocha a nádvoří',
            self::OtherArea => 'ostatní plocha',
        };
    }
}
