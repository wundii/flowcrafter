<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Wundii\Flower\Flower;
use Wundii\Flower\MethodEnum;

require __DIR__ . '/../vendor/autoload.php';

$route = Flower::router();

$route->add(
    '/',
    MethodEnum::GET,
    function (): JsonResponse {
        return new JsonResponse(
            [
                'status' => 'ok',
            ],
            200,
        );
    }
);

$route->add(
    '/ping',
    MethodEnum::GET,
    function (Request $request): JsonResponse {
        return new JsonResponse(
            [
                'pong' => $request->query->get('pong', true),
            ],
            200,
        );
    }
);

Flower::run();
