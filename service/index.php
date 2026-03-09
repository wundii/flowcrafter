<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Wundii\DataMapper\DataConfig;
use Wundii\DataMapper\DataMapper;
use Wundii\DataMapper\Enum\ApproachEnum;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;
use Wundii\Flower\Flower;
use Wundii\Flower\MethodEnum;

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

try {
    $storage->initializeDatabase();
} catch (Throwable $throwable) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Storage initialization failed',
        'message' => $throwable->getMessage(),
        'class' => get_class($throwable),
    ]);
    exit;
}

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

$observerPidFile = sys_get_temp_dir() . '/flowcrafter-observer.pid';

$route->add(
    '/api/info',
    MethodEnum::GET,
    function () use ($flowcrafterConfig, $observerPidFile): JsonResponse {
        $observerRunning = false;
        if (file_exists($observerPidFile)) {
            $pid = (int) file_get_contents($observerPidFile);
            $observerRunning = $pid > 0 && file_exists('/proc/' . $pid);
        }

        return new JsonResponse([
            'description' => $flowcrafterConfig->getServerDescription(),
            'observerRunning' => $observerRunning,
        ]);
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

// GET /api/queue/count
$route->add(
    '/api/queue/count',
    MethodEnum::GET,
    function () use ($storage): JsonResponse {
        return new JsonResponse([
            'count' => $storage->openQueues(),
        ]);
    }
);

// POST /api/flows/run  body: { flowHash, messageSource, message }
$route->add(
    '/api/flows/run',
    MethodEnum::POST,
    function (Request $request) use ($storage): JsonResponse {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
            ], 400);
        }

        $flowHash = Assert::string($body['flowHash'] ?? '');
        $messageSource = Assert::string($body['messageSource'] ?? '');
        $message = Assert::array($body['message'] ?? []);

        if ($flowHash === '' || $messageSource === '' || $message === []) {
            return new JsonResponse([
                'error' => 'flowHash, messageSource and message required',
            ], 400);
        }

        if (!class_exists($messageSource)) {
            return new JsonResponse([
                'error' => 'Unknown message class',
            ], 400);
        }

        $existingFlow = $storage->findFlowByHash($flowHash);
        if (!$existingFlow instanceof Flow) {
            return new JsonResponse([
                'error' => 'Flow not found',
            ], 404);
        }

        try {
            $dataConfig = new DataConfig(approachEnum: ApproachEnum::CONSTRUCTOR);
            $dataMapper = new DataMapper($dataConfig);
            $messageInstance = $dataMapper->array($message, $messageSource);

            if (!$messageInstance instanceof MessageInterface) {
                return new JsonResponse([
                    'error' => 'Invalid message class or data',
                ], 400);
            }

            $flowRunner = new FlowRunner(
                type: $existingFlow->getType(),
                flowSource: $existingFlow->getSource(),
                storage: $storage,
            );
        } catch (Throwable $throwable) {
            return new JsonResponse([
                'error' => $throwable->getMessage(),
            ], 500);
        }

        try {
            $flowRunner->run($messageInstance, $flowHash);
        } catch (Throwable $throwable) {
            // the exception is recorded in storage
        }

        return new JsonResponse([
            'success' => true,
            'runtimeHash' => $flowRunner->getFlow()?->getRuntimeHash(),
        ]);
    }
);

// POST /api/queue  body: { flowHash, messageSource, message }
$route->add(
    '/api/queue',
    MethodEnum::POST,
    function (Request $request) use ($storage): JsonResponse {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
            ], 400);
        }

        $flowHash = Assert::string($body['flowHash'] ?? '');
        $messageSource = Assert::string($body['messageSource'] ?? '');
        $message = Assert::array($body['message'] ?? []);

        if ($flowHash === '' || $messageSource === '' || $message === []) {
            return new JsonResponse([
                'error' => 'flowHash, messageSource and message required',
            ], 400);
        }

        if (!class_exists($messageSource)) {
            return new JsonResponse([
                'error' => 'Unknown message class',
            ], 400);
        }

        $existingFlow = $storage->findFlowByHash($flowHash);
        if (!$existingFlow instanceof Flow) {
            return new JsonResponse([
                'error' => 'Flow not found',
            ], 404);
        }

        try {
            /** @var class-string<object> $messageSource */
            $storage->appendObserveItem(
                type: $existingFlow->getType(),
                flowSource: $existingFlow->getSource(),
                flowHash: $flowHash,
                messageSource: $messageSource,
                message: $message,
            );
        } catch (Throwable $throwable) {
            return new JsonResponse([
                'error' => $throwable->getMessage(),
            ], 500);
        }

        return new JsonResponse([
            'queued' => true,
        ]);
    }
);

Flower::run($flowcrafterConfig->getServerSecret());
