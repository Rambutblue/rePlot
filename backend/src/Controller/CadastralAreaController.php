<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CadastralAreaRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CadastralAreaController
{
    public function __construct(private CadastralAreaRepository $areas)
    {
    }

    #[Route('/api/cadastral-areas', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn (array $area): array => [
                'code' => $area['code'],
                'name' => $area['name'],
                'municipality' => $area['municipality_code'] !== null
                    ? ['code' => $area['municipality_code'], 'name' => $area['municipality_name']]
                    : null,
                'status' => $area['status'],
                'parcelCount' => $area['parcel_count'],
                'bbox' => $area['west'] !== null
                    ? [$area['west'], $area['south'], $area['east'], $area['north']]
                    : null,
            ],
            $this->areas->findAll(),
        ));
    }
}
