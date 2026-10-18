<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Repository\StagingRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StagingRepositoryTest extends KernelTestCase
{
    private const string LAND_TYPE = 'https://services.cuzk.cz/registry/codelist/LandTypeValue/';
    private const string LAND_USE = 'https://services.cuzk.cz/registry/codelist/LandUseValue/';
    private const string SQUARE = 'SRID=5514;MULTIPOLYGON(((-670680 -1012290,-670670 -1012290,-670670 -1012280,-670680 -1012280,-670680 -1012290)))';
    private const string BOWTIE = 'SRID=5514;MULTIPOLYGON(((-670700 -1012300,-670690 -1012290,-670700 -1012290,-670690 -1012300,-670700 -1012300)))';

    private Connection $connection;
    private StagingRepository $staging;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        $staging = self::getContainer()->get(StagingRepository::class);
        self::assertInstanceOf(Connection::class, $connection);
        self::assertInstanceOf(StagingRepository::class, $staging);
        $this->connection = $connection;
        $this->staging = $staging;

        $this->connection->beginTransaction();
        $this->connection->executeStatement("INSERT INTO cadastral_area (code, name) VALUES (659541, 'Jičín')");
        $this->connection->executeStatement('DROP TABLE IF EXISTS staging.cpx_parcel, staging.cpx_zoning');
        $this->connection->executeStatement(
            'CREATE TABLE staging.cpx_zoning (ogc_fid serial, label varchar, nationalcadastalzoningreference integer, geom geometry(MultiPolygon, 5514))',
        );
        $this->connection->executeStatement(
            'INSERT INTO staging.cpx_zoning (label, nationalcadastalzoningreference, geom) VALUES (:label, :code, :geom)',
            ['label' => 'Jičín', 'code' => 659541, 'geom' => self::SQUARE],
        );
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testStagedRowsAreMappedToParcels(): void
    {
        $this->createParcelTable(withLandUse: true);
        $this->stageParcel('CPX.61816674010', '1854/36', 132, self::LAND_TYPE.'Garden', null, self::SQUARE);
        $this->stageParcel('CPX.1753220604', 'st. 1', 4913, self::LAND_TYPE.'OtherArea', self::LAND_USE.'OtherRoads', self::BOWTIE);
        $version = (int) $this->connection->fetchOne('SELECT data_version FROM app_state');

        self::assertSame(2, $this->staging->replaceArea(659541));

        $parcels = $this->connection->fetchAllAssociative(
            'SELECT id, area_code, label, reference, area_m2, land_type, land_use, ST_SRID(geom) AS srid, ST_IsValid(geom) AS valid FROM parcel ORDER BY id',
        );
        self::assertSame([
            ['id' => 1753220604, 'area_code' => 659541, 'label' => 'st. 1', 'reference' => '659541-st. 1', 'area_m2' => 4913, 'land_type' => 'OtherArea', 'land_use' => 'OtherRoads', 'srid' => 3857, 'valid' => true],
            ['id' => 61816674010, 'area_code' => 659541, 'label' => '1854/36', 'reference' => '659541-1854/36', 'area_m2' => 132, 'land_type' => 'Garden', 'land_use' => null, 'srid' => 3857, 'valid' => true],
        ], $parcels);

        $area = $this->connection->fetchAssociative(
            'SELECT status, parcel_count, outline IS NOT NULL AS has_outline, imported_at IS NOT NULL AS imported FROM cadastral_area WHERE code = 659541',
        );
        self::assertSame(['status' => 'done', 'parcel_count' => 2, 'has_outline' => true, 'imported' => true], $area);
        self::assertSame($version + 1, (int) $this->connection->fetchOne('SELECT data_version FROM app_state'));
    }

    public function testReimportReplacesPreviousParcels(): void
    {
        $this->createParcelTable(withLandUse: true);
        $this->stageParcel('CPX.61816674010', '1854/36', 132, self::LAND_TYPE.'Garden', null, self::SQUARE);
        $this->staging->replaceArea(659541);

        $this->connection->executeStatement('TRUNCATE staging.cpx_parcel');
        $this->stageParcel('CPX.61816674011', '1854/37', 99, self::LAND_TYPE.'Garden', null, self::SQUARE);
        $this->staging->replaceArea(659541);

        self::assertSame([61816674011], $this->connection->fetchFirstColumn('SELECT id FROM parcel'));
    }

    public function testMissingOptionalColumnsBecomeNull(): void
    {
        $this->createParcelTable(withLandUse: false);
        $this->stageParcel('CPX.61816674010', '1854/36', 132, self::LAND_TYPE.'Garden', null, self::SQUARE);

        $this->staging->replaceArea(659541);

        self::assertNull($this->connection->fetchOne('SELECT land_use FROM parcel'));
    }

    public function testZoningOfAnotherAreaIsRejected(): void
    {
        $this->createParcelTable(withLandUse: true);
        $this->stageParcel('CPX.61816674010', '1854/36', 132, self::LAND_TYPE.'Garden', null, self::SQUARE);
        $this->connection->executeStatement("INSERT INTO cadastral_area (code, name) VALUES (770141, 'Vinice v Podkrkonoší')");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/expected 770141/');

        $this->staging->replaceArea(770141);
    }

    private function createParcelTable(bool $withLandUse): void
    {
        $this->connection->executeStatement(sprintf(
            'CREATE TABLE staging.cpx_parcel (ogc_fid serial, localid varchar, label varchar, nationalcadastralreference varchar, areavalue integer, landtype_href varchar, %s geom geometry(MultiPolygon, 5514))',
            $withLandUse ? 'landuse_href varchar,' : '',
        ));
    }

    private function stageParcel(string $localId, string $label, int $area, string $landType, ?string $landUse, string $geom): void
    {
        $values = [
            'localid' => $localId,
            'label' => $label,
            'nationalcadastralreference' => '659541-'.$label,
            'areavalue' => $area,
            'landtype_href' => $landType,
            'geom' => $geom,
        ];
        if ($landUse !== null) {
            $values['landuse_href'] = $landUse;
        }

        $this->connection->insert('staging.cpx_parcel', $values);
    }
}
