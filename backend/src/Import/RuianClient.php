<?php

declare(strict_types=1);

namespace App\Import;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RuianClient
{
    private const string BASE_URL = 'https://ags.cuzk.cz/arcgis/rest/services/RUIAN/Prohlizeci_sluzba_nad_daty_RUIAN/MapServer';
    private const int LAYER_CADASTRAL_AREA = 7;
    private const int LAYER_MUNICIPALITY = 12;

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * @return list<CadastralAreaInfo>
     */
    public function resolve(ImportScope $scope): array
    {
        if ($scope->districtCode !== null) {
            $municipalities = $this->municipalities(sprintf('okres = %d', $scope->districtCode));
            if ($municipalities === []) {
                throw new \RuntimeException(sprintf('District %d has no municipalities in RÚIAN.', $scope->districtCode));
            }
            $areas = $this->cadastralAreas(sprintf('obec IN (%s)', implode(',', array_keys($municipalities))));
        } else {
            $areas = $this->cadastralAreas(sprintf('kod IN (%s)', implode(',', $scope->cadastralAreaCodes)));
            $missing = array_diff($scope->cadastralAreaCodes, array_column($areas, 'kod'));
            if ($missing !== []) {
                throw new \RuntimeException(sprintf('Unknown cadastral area codes: %s.', implode(', ', $missing)));
            }
            $municipalities = $this->municipalities(sprintf('kod IN (%s)', implode(',', array_unique(array_column($areas, 'obec')))));
        }

        $result = array_map(
            static fn (array $area): CadastralAreaInfo => new CadastralAreaInfo(
                $area['kod'],
                $area['nazev'],
                $area['obec'],
                $municipalities[$area['obec']] ?? '',
            ),
            $areas,
        );
        usort($result, static fn (CadastralAreaInfo $a, CadastralAreaInfo $b): int => $a->code <=> $b->code);

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function municipalities(string $where): array
    {
        $names = [];
        foreach ($this->query(self::LAYER_MUNICIPALITY, $where, 'kod,nazev') as $row) {
            $names[(int) $row['kod']] = (string) $row['nazev'];
        }

        return $names;
    }

    /**
     * @return list<array{kod: int, nazev: string, obec: int}>
     */
    private function cadastralAreas(string $where): array
    {
        return array_map(
            static fn (array $row): array => [
                'kod' => (int) $row['kod'],
                'nazev' => (string) $row['nazev'],
                'obec' => (int) $row['obec'],
            ],
            $this->query(self::LAYER_CADASTRAL_AREA, $where, 'kod,nazev,obec'),
        );
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    private function query(int $layer, string $where, string $outFields): array
    {
        $data = $this->httpClient->request('GET', sprintf('%s/%d/query', self::BASE_URL, $layer), [
            'query' => [
                'where' => $where,
                'outFields' => $outFields,
                'returnGeometry' => 'false',
                'f' => 'json',
            ],
            'timeout' => 30,
        ])->toArray();

        if (isset($data['error'])) {
            throw new \RuntimeException(sprintf('RÚIAN query failed: %s', json_encode($data['error'], JSON_UNESCAPED_UNICODE)));
        }
        if (($data['exceededTransferLimit'] ?? false) === true) {
            throw new \RuntimeException('RÚIAN query exceeded the transfer limit.');
        }

        return array_values(array_column($data['features'] ?? [], 'attributes'));
    }
}
