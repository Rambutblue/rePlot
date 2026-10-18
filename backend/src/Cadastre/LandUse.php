<?php

declare(strict_types=1);

namespace App\Cadastre;

enum LandUse: string
{
    case Greenhouse = 'Greenhouse';
    case ForestTreeNursery = 'ForestTreeNursery';
    case TreePlantation = 'TreePlantation';
    case NonProductionForest = 'NonProductionForest';
    case ForestedAreaWithBuilding = 'ForestedAreaWithBuilding';
    case Pond = 'Pond';
    case NaturalWatercourse = 'NaturalWatercourse';
    case ArtificialWatercourse = 'ArtificialWatercourse';
    case NaturalWaterTank = 'NaturalWaterTank';
    case ArtificialWaterTank = 'ArtificialWaterTank';
    case WaterloggedArea = 'WaterloggedArea';
    case SharedYard = 'SharedYard';
    case Ruins = 'Ruins';
    case Railway = 'Railway';
    case Highway = 'Highway';
    case Road = 'Road';
    case OtherRoads = 'OtherRoads';
    case OtherTrafficAreas = 'OtherTrafficAreas';
    case Greenery = 'Greenery';
    case RecreationArea = 'RecreationArea';
    case Cemetery = 'Cemetery';
    case CulturalArea = 'CulturalArea';
    case HandlingArea = 'HandlingArea';
    case MiningArea = 'MiningArea';
    case Dump = 'Dump';
    case OtherArea = 'OtherArea';
    case InfertileLand = 'InfertileLand';
    case WaterSurfaceWithBuilding = 'WaterSurfaceWithBuilding';
    case PhotovoltaicPowerPlant = 'PhotovoltaicPowerPlant';

    public function label(): string
    {
        return match ($this) {
            self::Greenhouse => 'skleník, pařeniště',
            self::ForestTreeNursery => 'školka',
            self::TreePlantation => 'plantáž dřevin',
            self::NonProductionForest => 'les jiný než hospodářský',
            self::ForestedAreaWithBuilding => 'lesní pozemek, na kterém je budova',
            self::Pond => 'rybník',
            self::NaturalWatercourse => 'koryto vodního toku přirozené nebo upravené',
            self::ArtificialWatercourse => 'koryto vodního toku umělé',
            self::NaturalWaterTank => 'vodní nádrž přírodní',
            self::ArtificialWaterTank => 'vodní nádrž umělá',
            self::WaterloggedArea => 'zamokřená plocha',
            self::SharedYard => 'společný dvůr',
            self::Ruins => 'zbořeniště',
            self::Railway => 'dráha',
            self::Highway => 'dálnice',
            self::Road => 'silnice',
            self::OtherRoads => 'ostatní komunikace',
            self::OtherTrafficAreas => 'ostatní dopravní plocha',
            self::Greenery => 'zeleň',
            self::RecreationArea => 'sportoviště a rekreační plocha',
            self::Cemetery => 'hřbitov, urnový háj',
            self::CulturalArea => 'kulturní a osvětová plocha',
            self::HandlingArea => 'manipulační plocha',
            self::MiningArea => 'dobývací prostor',
            self::Dump => 'skládka',
            self::OtherArea => 'jiná plocha',
            self::InfertileLand => 'neplodná půda',
            self::WaterSurfaceWithBuilding => 'vodní plocha, na které je budova',
            self::PhotovoltaicPowerPlant => 'fotovoltaická elektrárna',
        };
    }
}
