<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

final class HealthController
{
    public function index(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
        ], 200);
    }

    public function ping(): JsonResponse
    {
        return new JsonResponse('pong', 200);
    }
}
