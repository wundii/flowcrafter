<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;
use Wundii\Flower\Flower;
use Wundii\Flower\MethodEnum;

require __DIR__ . '/../vendor/autoload.php';

// CORS for development (Vite dev server on different port)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$flowcrafterConfig = new FlowcrafterConfig();
$configFile = __DIR__ . '/../flowcrafter.php';
if (file_exists($configFile)) {
    $configClosure = require $configFile;
    $configClosure($flowcrafterConfig);
}

$storage = $flowcrafterConfig->getStorage();
$storage->initializeDatabase();

$serializeEntity = static fn (FlowEntity $flowEntity): array => [
    'flowHash' => $flowEntity->flowHash,
    'flowType' => $flowEntity->flowType,
    'flowSource' => $flowEntity->flowSource,
    'flowSubject' => $flowEntity->flowSubject,
    'time' => $flowEntity->time->format(DateTimeInterface::ATOM),
];

$route = Flower::router();

$route->add(
    '/',
    MethodEnum::GET,
    function (): JsonResponse {
        return new JsonResponse([
            'status' => 'ok',
        ], 200);
    }
);

$route->add(
    '/api/ping',
    MethodEnum::GET,
    function (): JsonResponse {
        return new JsonResponse('pong', 200);
    }
);

// GET /api/flows[?sort=asc|desc&top=1000&source=App\YourFlow]
$route->add(
    '/api/flows',
    MethodEnum::GET,
    function (Request $request) use ($storage, $serializeEntity): JsonResponse {
        $sort = $request->query->get('sort', 'desc') === 'asc' ? SortEnum::ASC : SortEnum::DESC;
        $top = max(1, min(10000, (int) $request->query->get('top', 1000)));
        $source = $request->query->get('source');

        $flows = $source !== null
            ? $storage->findFlowsBySource($source, $sort, $top)
            : $storage->findAllFlows($sort, $top);

        return new JsonResponse(array_map($serializeEntity, iterator_to_array($flows)));
    }
);

// GET /api/flows/detail?hash=<flowHash> | ?runtimeHash=<flowRuntimeHash>
$route->add(
    '/api/flows/detail',
    MethodEnum::GET,
    function (Request $request) use ($storage): JsonResponse {
        $hash = $request->query->get('hash');
        $runtimeHash = $request->query->get('runtimeHash');

        if ($runtimeHash !== null && $runtimeHash !== '') {
            $flow = $storage->findFlowByRuntimeHash($runtimeHash);
        } elseif ($hash !== null && $hash !== '') {
            $flow = $storage->findFlowByHash($hash);
        } else {
            return new JsonResponse([
                'error' => 'hash or runtimeHash parameter required',
            ], 400);
        }

        if (!$flow instanceof Flow) {
            return new JsonResponse([
                'error' => 'Flow not found',
            ], 404);
        }

        return new JsonResponse($flow);
    }
);

// GET /api/exceptions[?sort=asc|desc&top=1000&flowHash=<hash>]
$route->add(
    '/api/exceptions',
    MethodEnum::GET,
    function (Request $request) use ($storage): JsonResponse {
        $sort = $request->query->get('sort', 'desc') === 'asc' ? SortEnum::ASC : SortEnum::DESC;
        $top = max(1, min(10000, (int) $request->query->get('top', 1000)));
        $flowHash = $request->query->get('flowHash');

        $exceptions = $flowHash !== null
            ? $storage->findExceptionsByFlowHash($flowHash, $sort, $top)
            : $storage->findAllExceptions($sort, $top);

        return new JsonResponse(array_values(iterator_to_array($exceptions)));
    }
);

Flower::run($flowcrafterConfig->getServerSecret());
