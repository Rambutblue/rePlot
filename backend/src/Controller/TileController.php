<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AppStateRepository;
use App\Repository\TileRepository;
use App\Tile\Tile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class TileController
{
    public function __construct(
        private TileRepository $tiles,
        private AppStateRepository $appState,
    ) {
    }

    #[Route('/api/tiles/{z}/{x}/{y}.pbf', requirements: ['z' => '\d+', 'x' => '\d+', 'y' => '\d+'], methods: ['GET'])]
    public function __invoke(int $z, int $x, int $y, Request $request): Response
    {
        try {
            $tile = new Tile($z, $x, $y);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        $data = $this->tiles->render($tile);

        $response = $data === ''
            ? new Response(null, Response::HTTP_NO_CONTENT)
            : new Response($data, Response::HTTP_OK, ['Content-Type' => 'application/vnd.mapbox-vector-tile']);

        $current = $request->query->get('v') === (string) $this->appState->dataVersion();
        $response->setPublic();
        $response->setMaxAge($current ? 31536000 : 60);
        if ($current) {
            $response->setImmutable();
        }

        return $response;
    }
}
