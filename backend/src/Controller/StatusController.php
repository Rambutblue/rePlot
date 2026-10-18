<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AppStateRepository;
use App\Repository\CadastralAreaRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class StatusController
{
    public function __construct(
        private CadastralAreaRepository $areas,
        private AppStateRepository $appState,
    ) {
    }

    #[Route('/api/status', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $progress = $this->areas->importProgress();

        return new JsonResponse([
            'dataVersion' => $this->appState->dataVersion(),
            'total' => $progress['total'],
            'done' => $progress['done'],
            'failed' => $progress['failed'],
            'pending' => $progress['pending'],
            'importing' => $progress['pending'] > 0,
            'current' => $progress['current_code'] !== null
                ? ['code' => $progress['current_code'], 'name' => $progress['current_name']]
                : null,
        ]);
    }
}
