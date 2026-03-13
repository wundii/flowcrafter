<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wundii\DataMapper\DataConfig;
use Wundii\DataMapper\DataMapper;
use Wundii\DataMapper\Enum\ApproachEnum;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfigRequirer;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;
use Wundii\Flower\Flower;
use Wundii\Flower\MethodEnum;

require getcwd() . '/vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $flowcrafterConfigFile = $_ENV['FLOWCRAFTER_CONFIG'] ?? null;
    $bootstrapConfig = new BootstrapConfig(is_string($flowcrafterConfigFile) ? $flowcrafterConfigFile : null);
    $bootstrapConfigRequirer = new BootstrapConfigRequirer($bootstrapConfig);
    $flowcrafterConfig = $bootstrapConfigRequirer->loadConfigFile(new FlowcrafterConfig());
} catch (Throwable $throwable) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $throwable->getMessage(),
    ]);
    exit;
}

try {
    $storage = $flowcrafterConfig->getStorage();
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
    'time' => $flowEntity->time->format(DateTimeInterface::RFC3339_EXTENDED),
    'exceptionCount' => $flowEntity->exceptionCount,
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

// GET /metrics  — Prometheus / OpenMetrics exposition format
$route->add(
    '/metrics',
    MethodEnum::GET,
    function () use ($storage, $flowcrafterConfig, $observerPidFile): Response {
        $observerRunning = false;
        if (file_exists($observerPidFile)) {
            $pid = (int) file_get_contents($observerPidFile);
            $observerRunning = $pid > 0 && file_exists('/proc/' . $pid);
        }

        $queueSize = $storage->openQueues();

        $description = $flowcrafterConfig->getServerDescription() ?? '';
        $safeDescription = str_replace(['"', "\n", "\r"], ['\"', ' ', ' '], $description);

        $lines = [];

        $lines[] = '# HELP flowcrafter_info FlowCrafter service information';
        $lines[] = '# TYPE flowcrafter_info gauge';
        $lines[] = sprintf('flowcrafter_info{description="%s"} 1', $safeDescription);

        $lines[] = '# HELP flowcrafter_observer_up Whether the FlowCrafter observer process is running (1 = up, 0 = down)';
        $lines[] = '# TYPE flowcrafter_observer_up gauge';
        $lines[] = sprintf('flowcrafter_observer_up %d', $observerRunning ? 1 : 0);

        $lines[] = '# HELP flowcrafter_queue_size Number of items currently pending in the queue';
        $lines[] = '# TYPE flowcrafter_queue_size gauge';
        $lines[] = sprintf('flowcrafter_queue_size %d', $queueSize);

        $body = implode("\n", $lines) . "\n";

        return new Response($body, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
);

// GET /api/flows[?sort=asc|desc&top=1000&skip=0&type=flow.example&from=ISO8601&to=ISO8601]
$route->add(
    '/api/flows',
    MethodEnum::GET,
    function (Request $request) use ($storage, $serializeEntity): JsonResponse {
        $sort = $request->query->get('sort', 'desc') === 'asc' ? SortEnum::ASC : SortEnum::DESC;
        $top = max(1, min(10000, (int) $request->query->get('top', 1000)));
        $skip = max(0, (int) $request->query->get('skip', 0));
        $type = $request->query->get('type');
        $fromStr = $request->query->get('from');
        $toStr = $request->query->get('to');
        $from = is_string($fromStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $fromStr) : null;
        $to = is_string($toStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $toStr) : null;
        $from = $from instanceof DateTimeImmutable ? $from : null;
        $to = $to instanceof DateTimeImmutable ? $to : null;

        $flows = $type !== null
            ? $storage->findFlowsByType($type, $sort, $top + 1, $skip, $from, $to)
            : $storage->findAllFlows($sort, $top + 1, $skip, $from, $to);

        $items = array_map($serializeEntity, iterator_to_array($flows));
        $hasMore = count($items) > $top;
        if ($hasMore) {
            array_pop($items);
        }

        $total = $type !== null
            ? $storage->countFlowsByType($type)
            : $storage->countFlows();

        return new JsonResponse([
            'items' => $items,
            'total' => $total,
            'hasMore' => $hasMore,
        ]);
    }
);

// GET /api/flows/detail?hash=<flowHash> | ?runtimeHash=<flowRuntimeHash>
$route->add(
    '/api/flows/detail',
    MethodEnum::GET,
    function (Request $request) use ($storage): JsonResponse {
        $hash = Assert::string($request->query->get('hash') ?? '');
        $runtimeHash = Assert::string($request->query->get('runtimeHash') ?? '');

        if ($hash === '' && $runtimeHash === '') {
            return new JsonResponse([
                'error' => 'hash or runtimeHash parameter required',
            ], 400);
        }

        $flow = $hash > ''
            ? $storage->findFlowByHash($hash)
            : $storage->findFlowByRuntimeHash($runtimeHash);

        if (!$flow instanceof Flow) {
            return new JsonResponse([
                'error' => 'Flow not found',
            ], 404);
        }

        return new JsonResponse($flow);
    }
);

// GET /api/flows/schema
$route->add(
    '/api/schemas',
    MethodEnum::GET,
    function () use ($storage): JsonResponse {
        $schemas = $storage->findAllSchemas();

        return new JsonResponse(iterator_to_array($schemas));
    }
);

// GET /api/flows/schema?className=object
$route->add(
    '/api/schema/stub-source',
    MethodEnum::GET,
    function (Request $request): JsonResponse {
        $className = $request->query->get('className', '');
        $className = str_starts_with($className, '\\') ? $className : '\\' . $className;

        if (!class_exists($className)) {
            return new JsonResponse([
                'error' => 'The class not found',
            ], 404);
        }

        if (!is_subclass_of($className, StubInterface::class)) {
            return new JsonResponse([
                'error' => 'The class does not implement StubInterface',
            ], 400);
        }

        $ref = new ReflectionClass($className);
        $file = (string) $ref->getFileName();

        if (!file_exists($file)) {
            return new JsonResponse([
                'error' => 'The file not found',
            ], 400);
        }

        $content = file_get_contents($file);
        if (!is_string($content)) {
            return new JsonResponse([
                'error' => 'The file could not be read.',
            ], 400);
        }

        return new JsonResponse([
            'source' => $content,
        ]);
    }
);

// GET /api/exceptions[?sort=asc|desc&top=1000&skip=0&flowHash=<hash>&from=ISO8601&to=ISO8601]
$route->add(
    '/api/exceptions',
    MethodEnum::GET,
    function (Request $request) use ($storage): JsonResponse {
        $sort = $request->query->get('sort', 'desc') === 'asc' ? SortEnum::ASC : SortEnum::DESC;
        $top = max(1, min(10000, (int) $request->query->get('top', 1000)));
        $skip = max(0, (int) $request->query->get('skip', 0));
        $flowHash = $request->query->get('flowHash');
        $fromStr = $request->query->get('from');
        $toStr = $request->query->get('to');
        $from = is_string($fromStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $fromStr) : null;
        $to = is_string($toStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $toStr) : null;
        $from = $from instanceof DateTimeImmutable ? $from : null;
        $to = $to instanceof DateTimeImmutable ? $to : null;

        $exceptions = $flowHash !== null
            ? $storage->findExceptionsByFlowHash($flowHash, $sort, $top + 1, $skip, $from, $to)
            : $storage->findAllExceptions($sort, $top + 1, $skip, $from, $to);

        $items = array_values(iterator_to_array($exceptions));
        $hasMore = count($items) > $top;
        if ($hasMore) {
            array_pop($items);
        }

        $total = $flowHash !== null
            ? $storage->countExceptionsByFlowHash($flowHash)
            : $storage->countExceptions();

        return new JsonResponse([
            'items' => $items,
            'total' => $total,
            'hasMore' => $hasMore,
        ]);
    }
);

// GET /api/queues[?sort=asc|desc]
$route->add(
    '/api/queues',
    MethodEnum::GET,
    function (Request $request) use ($storage): JsonResponse {
        $sort = $request->query->get('sort', 'desc') === 'asc' ? SortEnum::ASC : SortEnum::DESC;

        $result = [];
        foreach ($storage->findAllQueues($sort) as $item) {
            $result[] = [
                'queueId' => $item->getQueueId(),
                'type' => $item->getType(),
                'flowSource' => $item->getFlowSource(),
                'flowHash' => $item->getFlowHash(),
                'messageSource' => $item->getMessageSource(),
                'message' => $item->getMessage(),
            ];
        }

        return new JsonResponse($result);
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
    function (Request $request) use ($storage, $flowcrafterConfig): JsonResponse {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
            ], 400);
        }

        $flowHash = Assert::string($body['flowHash'] ?? '');
        $messageSource = Assert::string($body['messageSource'] ?? '');
        $message = Assert::array($body['message'] ?? []);
        $messageReturn = null;

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

        if (!$existingFlow->isExecutable()) {
            return new JsonResponse([
                'error' => 'Flow is not executable',
            ], 400);
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
                dependenciesInjection: $flowcrafterConfig->getDependencyInjections(),
            );
        } catch (Throwable $throwable) {
            return new JsonResponse([
                'error' => $throwable->getMessage(),
            ], 500);
        }

        try {
            $messageReturn = $flowRunner->run($messageInstance, $flowHash);
        } catch (Throwable $throwable) {
            // the exception is recorded in storage
        }

        return new JsonResponse([
            'success' => true,
            'runtimeHash' => $flowRunner->getFlow()?->getRuntimeHash(),
            'messageReturn' => $messageReturn instanceof MessageReturnInterface ? $messageReturn : null,
        ]);
    }
);

// POST /api/queue  body: { flowHash, messageSource, message, type, flowSource }
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

        $flowHash = Assert::nullOrString($body['flowHash'] ?? null);
        $messageSource = Assert::string($body['messageSource'] ?? '');
        $message = Assert::array($body['message'] ?? []);
        $type = Assert::string($body['type'] ?? '');
        $flowSource = Assert::string($body['flowSource'] ?? '');

        if ($flowHash !== null && $flowHash !== '') {
            $flow = $storage->findFlowByHash($flowHash);
            if (!$flow instanceof Flow) {
                return new JsonResponse([
                    'error' => 'Flow not found',
                ], 404);
            }

            $type = $flow->getType();
            $flowSource = $flow->getSource();
        }

        if ($type === '' || $flowSource === '' || $messageSource === '' || $message === []) {
            return new JsonResponse([
                'error' => 'type, flowSource, messageSource and message required',
            ], 400);
        }

        if (!class_exists($messageSource)) {
            return new JsonResponse([
                'error' => 'Unknown message class',
            ], 400);
        }

        try {
            /**
             * @var class-string $flowSource
             * @var class-string $messageSource
             */
            $storage->appendObserveItem(
                type: $type,
                flowSource: $flowSource,
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
