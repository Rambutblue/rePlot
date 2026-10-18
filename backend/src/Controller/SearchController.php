<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SearchRepository;
use App\Search\SearchQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SearchController
{
    private const int LIMIT = 10;

    public function __construct(private SearchRepository $search)
    {
    }

    #[Route('/api/search', methods: ['GET'])]
    public function __invoke(#[MapQueryParameter] string $q = ''): JsonResponse
    {
        $query = SearchQuery::parse(mb_substr($q, 0, 100));
        if ($query === null) {
            return new JsonResponse(['results' => []]);
        }

        $results = [];
        if ($query->parcelLabel !== null) {
            foreach ($this->search->parcels($query, self::LIMIT) as $parcel) {
                $results[] = [
                    'type' => 'parcel',
                    'id' => $parcel['id'],
                    'label' => $parcel['label'],
                    'detail' => sprintf('k.ú. %s', $parcel['area_name']),
                    'center' => [$parcel['lng'], $parcel['lat']],
                    'bbox' => [$parcel['west'], $parcel['south'], $parcel['east'], $parcel['north']],
                ];
            }
        } elseif ($query->areaName !== null) {
            foreach ($this->search->areas($query->areaName, self::LIMIT) as $area) {
                $results[] = [
                    'type' => 'area',
                    'id' => $area['code'],
                    'label' => $area['name'],
                    'detail' => $area['municipality_name'] !== null ? sprintf('obec %s', $area['municipality_name']) : null,
                    'center' => [$area['lng'], $area['lat']],
                    'bbox' => [$area['west'], $area['south'], $area['east'], $area['north']],
                ];
            }
        }

        return new JsonResponse(['results' => $results]);
    }
}
