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
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowStatsEntity;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;
use Wundii\Flower\Flower;
use Wundii\Flower\MethodEnum;

$autoloadCandidates = [
    dirname(__DIR__) . '/vendor/autoload.php',    // flowcrafter repo / FrankenPHP root
    dirname(__DIR__, 4) . '/vendor/autoload.php', // installed as composer package
    getcwd() . '/vendor/autoload.php',             // PHP built-in server (dev)
];
$autoloadFile = null;
foreach ($autoloadCandidates as $autoloadCandidate) {
    if (file_exists($autoloadCandidate)) {
        $autoloadFile = $autoloadCandidate;
        break;
    }
}

if ($autoloadFile === null) {
    throw new RuntimeException('vendor/autoload.php not found');
}

require $autoloadFile;

$flowcrafterConfigFile = $_ENV['FLOWCRAFTER_CONFIG'] ?? null;
$bootstrapConfig = new BootstrapConfig(is_string($flowcrafterConfigFile) ? $flowcrafterConfigFile : null);
$bootstrapConfigRequirer = new BootstrapConfigRequirer($bootstrapConfig);
$flowcrafterConfig = $bootstrapConfigRequirer->loadConfigFile(new FlowcrafterConfig());

$storage = $flowcrafterConfig->getStorage();

$serializeEntity = static fn (FlowEntity $flowEntity): array => [
    'flowHash' => $flowEntity->flowHash,
    'flowType' => $flowEntity->flowType,
    'flowSource' => $flowEntity->flowSource,
    'flowSubject' => $flowEntity->flowSubject,
    'time' => $flowEntity->time->format(DateTimeInterface::RFC3339_EXTENDED),
    'timeLastRun' => $flowEntity->timeLastRun->format(DateTimeInterface::RFC3339_EXTENDED),
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

$observerHeartbeatDir = sys_get_temp_dir() . '/flowcrafter';
$getObserverWorkers = static function () use ($observerHeartbeatDir): array {
    $workers = [];
    foreach (glob($observerHeartbeatDir . '/observer.*.heartbeat') ?: [] as $file) {
        $mtime = filemtime($file);
        if ($mtime === false) {
            continue;
        }

        if ((time() - $mtime) > 60) {
            continue;
        }

        $baseName = basename($file, '.heartbeat');
        if (!preg_match('/^observer\.(.+)\.(\d+)$/', $baseName, $m)) {
            continue;
        }

        $workers[] = [
            'hostname' => $m[1],
            'pid' => (int) $m[2],
            'lastHeartbeat' => date(DateTimeInterface::RFC3339, $mtime),
        ];
    }

    return $workers;
};

$route->add(
    '/api/info',
    MethodEnum::GET,
    function () use ($flowcrafterConfig, $storage, $getObserverWorkers): JsonResponse {
        return new JsonResponse([
            'version' => FlowConsole::vendorVersion(),
            'php' => PHP_VERSION,
            'storage' => (new ReflectionClass($storage))->getShortName(),
            'description' => $flowcrafterConfig->getServerDescription(),
            'workers' => $getObserverWorkers(),
        ]);
    }
);

// GET /metrics  — Prometheus / OpenMetrics exposition format
$route->add(
    '/metrics',
    MethodEnum::GET,
    function () use ($storage, $flowcrafterConfig, $getObserverWorkers): Response {
        $observerWorkers = $getObserverWorkers();

        $queueSize = $storage->openQueues();
        $flowsTotal = $storage->countFlows();
        $exceptions7d = $storage->countExceptions(
            from: new DateTimeImmutable('-7 days'),
        );

        $description = $flowcrafterConfig->getServerDescription() ?? '';
        $safeDescription = str_replace(['"', "\n", "\r"], ['\"', ' ', ' '], $description);
        $storageType = strtolower((new ReflectionClass($storage))->getShortName());

        $lines = [];

        $lines[] = '# HELP flowcrafter_info FlowCrafter service information';
        $lines[] = '# TYPE flowcrafter_info gauge';
        $lines[] = sprintf('flowcrafter_info{description="%s",storage="%s"} 1', $safeDescription, $storageType);

        $lines[] = '# HELP flowcrafter_observer_up Whether the FlowCrafter observer process is running (1 = up, 0 = down)';
        $lines[] = '# TYPE flowcrafter_observer_up gauge';
        $lines[] = sprintf('flowcrafter_observer_up %d', $observerWorkers !== [] ? 1 : 0);

        $lines[] = '# HELP flowcrafter_observer_workers Number of active observer worker processes';
        $lines[] = '# TYPE flowcrafter_observer_workers gauge';
        $lines[] = sprintf('flowcrafter_observer_workers %d', count($observerWorkers));

        $lines[] = '# HELP flowcrafter_queue_size Number of items currently pending in the queue';
        $lines[] = '# TYPE flowcrafter_queue_size gauge';
        $lines[] = sprintf('flowcrafter_queue_size %d', $queueSize);

        $lines[] = '# HELP flowcrafter_flows_total Total number of flow instances';
        $lines[] = '# TYPE flowcrafter_flows_total gauge';
        $lines[] = sprintf('flowcrafter_flows_total %d', $flowsTotal);

        $lines[] = '# HELP flowcrafter_exceptions_7d Number of exceptions in the last 7 days';
        $lines[] = '# TYPE flowcrafter_exceptions_7d gauge';
        $lines[] = sprintf('flowcrafter_exceptions_7d %d', $exceptions7d);

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

// GET /api/flows/stats[?from=ISO8601&to=ISO8601&type=flow.example]
$route->add(
    '/api/flows/stats',
    MethodEnum::GET,
    function (Request $request) use ($storage): JsonResponse {
        $fromStr = $request->query->get('from');
        $toStr = $request->query->get('to');
        $from = is_string($fromStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $fromStr) : null;
        $to = is_string($toStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $toStr) : null;
        $from = $from instanceof DateTimeImmutable ? $from : null;
        $to = $to instanceof DateTimeImmutable ? $to : null;
        $type = $request->query->get('type');

        $stats = iterator_to_array($storage->findFlowStats($from, $to, $type));

        return new JsonResponse(array_map(
            static fn (FlowStatsEntity $flowStatsEntity): array => [
                'date' => $flowStatsEntity->date,
                'instances' => $flowStatsEntity->instances,
                'runs' => $flowStatsEntity->runs,
            ],
            $stats,
        ));
    }
);

// GET /api/flows/search?subject=<query>[&top=10]
$route->add(
    '/api/flows/search',
    MethodEnum::GET,
    function (Request $request) use ($storage, $serializeEntity): JsonResponse {
        $subject = Assert::string($request->query->get('subject') ?? '');

        if ($subject === '') {
            return new JsonResponse([
                'items' => [],
                'total' => 0,
                'hasMore' => false,
            ]);
        }

        $top = max(1, min(100, (int) $request->query->get('top', 10)));

        $flows = $storage->findFlowsBySubject($subject, SortEnum::DESC, $top + 1);
        $items = array_map($serializeEntity, iterator_to_array($flows));
        $hasMore = count($items) > $top;
        if ($hasMore) {
            array_pop($items);
        }

        $total = $storage->countFlowsBySubject($subject);

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

// GET /api/flows/schema?className=object | ?stubHash=hash
$route->add(
    '/api/schema/stub-source',
    MethodEnum::GET,
    function (Request $request) use ($storage): JsonResponse {
        $className = $request->query->get('className', '');
        $className = $className && !str_starts_with($className, '\\') ? '\\' . $className : $className;

        if (class_exists($className)) {
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
                'current' => true,
                'source' => $content,
            ]);
        }

        $stubHash = $request->query->get('stubHash', '');

        $stubSourceEntity = $storage->findStubSourceByHash($stubHash);

        if (!$stubSourceEntity instanceof StubSourceEntity) {
            return new JsonResponse([
                'error' => 'Stub source not found',
            ], 404);
        }

        $current = class_exists($stubSourceEntity->stubSource);
        $source = $stubSourceEntity->sourceContent;

        if ($current) {
            $ref = new ReflectionClass($stubSourceEntity->stubSource);
            $file = (string) $ref->getFileName();

            $current = file_exists($file);
            $currentSource = file_get_contents($file);
            $current = $current && is_string($currentSource);
            $current = $current && $source === $currentSource;
        }

        return new JsonResponse([
            'current' => $current,
            'source' => $source,
        ]);
    }
);

// GET /api/flows/schema?stubSource=className
$route->add(
    '/api/schema/stub-sources',
    MethodEnum::GET,
    function (Request $request) use ($storage): JsonResponse {
        $stubSource = $request->query->get('stubSource', '');

        /** @var class-string $stubSource */
        $stubSources = $storage->findStubSourcesByStubSource($stubSource);

        $result = [];
        foreach ($stubSources as $stubSource) {
            $current = class_exists($stubSource->stubSource);
            $source = $stubSource->sourceContent;

            if ($current) {
                $ref = new ReflectionClass($stubSource->stubSource);
                $file = (string) $ref->getFileName();

                $current = file_exists($file);
                $currentSource = file_get_contents($file);
                $current = $current && is_string($currentSource);
                $current = $current && $source === $currentSource;
            }

            $result[] = [
                'current' => $current,
                'source' => $source,
                'time' => $stubSource->time->format(DateTimeInterface::RFC3339_EXTENDED),
            ];
        }

        return new JsonResponse($result);
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
        /** @var class-string[] $includeStubs */
        $includeStubs = Assert::array($body['includeStubs'] ?? []);
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
                flowSubject: $existingFlow->getSubject(),
                storage: $storage,
                dependenciesInjection: $flowcrafterConfig->getDependencyInjections(),
            );
        } catch (Throwable $throwable) {
            return new JsonResponse([
                'error' => $throwable->getMessage(),
            ], 500);
        }

        try {
            ob_start();
            $messageReturn = $flowRunner->run(
                message: $messageInstance,
                flowHash: $flowHash,
                includeStubs: $includeStubs,
            );
        } catch (Throwable $throwable) {
            // the exception is recorded in storage
        } finally {
            ob_end_clean();
        }

        return new JsonResponse([
            'success' => true,
            'runtimeHash' => $flowRunner->getFlow()?->getRuntimeHash(),
            'messageReturn' => $messageReturn instanceof MessageReturnInterface ? $messageReturn : null,
        ]);
    }
);

// POST /api/queue  body: { flowHash, messageSource, message, type, flowSource, flowSubject }
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
        /** @var class-string[] $includeStubs */
        $includeStubs = Assert::array($body['includeStubs'] ?? []);
        $type = Assert::string($body['type'] ?? '');
        $flowSource = Assert::string($body['flowSource'] ?? '');
        $flowSubject = Assert::nullOrString($body['flowSubject'] ?? null);

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
                includeStubs: $includeStubs,
                flowSubject: $flowSubject,
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

return $flowcrafterConfig;
