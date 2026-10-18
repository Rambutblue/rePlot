<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cadastre\LandType;
use App\Cadastre\LandUse;
use App\Repository\ParcelRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ParcelController
{
    private const string VDP_URL = 'https://vdp.cuzk.cz/vdp/ruian/parcely/%d';

    public function __construct(private ParcelRepository $parcels)
    {
    }

    #[Route('/api/parcels/{id}', requirements: ['id' => '\d{1,18}'], methods: ['GET'])]
    public function __invoke(int $id): JsonResponse
    {
        $parcel = $this->parcels->find($id);
        if ($parcel === null) {
            throw new NotFoundHttpException(sprintf('Parcel %d not found.', $id));
        }

        $landType = $parcel['land_type'] !== null ? LandType::tryFrom($parcel['land_type']) : null;
        $landUse = $parcel['land_use'] !== null ? LandUse::tryFrom($parcel['land_use']) : null;

        return new JsonResponse([
            'id' => $parcel['id'],
            'label' => $parcel['label'],
            'reference' => $parcel['reference'],
            'areaM2' => $parcel['area_m2'],
            'landType' => $parcel['land_type'] !== null
                ? ['code' => $parcel['land_type'], 'name' => $landType?->label() ?? $parcel['land_type']]
                : null,
            'landUse' => $parcel['land_use'] !== null
                ? ['code' => $parcel['land_use'], 'name' => $landUse?->label() ?? $parcel['land_use']]
                : null,
            'cadastralArea' => ['code' => $parcel['area_code'], 'name' => $parcel['area_name']],
            'municipality' => $parcel['municipality_code'] !== null
                ? ['code' => $parcel['municipality_code'], 'name' => $parcel['municipality_name']]
                : null,
            'center' => [$parcel['lng'], $parcel['lat']],
            'bbox' => [$parcel['west'], $parcel['south'], $parcel['east'], $parcel['north']],
            'vdpUrl' => sprintf(self::VDP_URL, $parcel['id']),
        ]);
    }
}
