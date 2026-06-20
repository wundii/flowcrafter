<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowDiscovery;
use Wundii\Flowcrafter\FlowPreflight;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Projection\ProjectionDiscovery;

final class FlowController
{
    public function __construct(
        private readonly FlowcrafterConfig $flowcrafterConfig,
        private readonly StorageInterface $storage,
        private readonly QueueInterface $queue,
        private readonly FlowPreflight $flowPreflight,
    ) {
    }

    public function list(Request $request): JsonResponse
    {
        $sort = $request->query->get('sort', 'desc') === 'asc' ? SortEnum::ASC : SortEnum::DESC;
        $top = max(1, min(10000, (int) $request->query->get('top', 1000)));
        $skip = max(0, (int) $request->query->get('skip', 0));
        $type = $request->query->get('type');
        $status = $request->query->get('status');
        $fromStr = $request->query->get('from');
        $toStr = $request->query->get('to');
        $from = is_string($fromStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $fromStr) : null;
        $to = is_string($toStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $toStr) : null;
        $from = $from instanceof DateTimeImmutable ? $from : null;
        $to = $to instanceof DateTimeImmutable ? $to : null;

        $flows = $type !== null
            ? $this->storage->findFlowsByType($type, $sort, $top + 1, $skip, $from, $to, $status)
            : $this->storage->findAllFlows($sort, $top + 1, $skip, $from, $to, $status);

        $items = iterator_to_array($flows);
        $hasMore = count($items) > $top;
        if ($hasMore) {
            array_pop($items);
        }

        $total = $type !== null
            ? $this->storage->countFlowsByType($type, $from, $to, $status)
            : $this->storage->countFlows($from, $to, $status);

        return new JsonResponse([
            'items' => $items,
            'total' => $total,
            'hasMore' => $hasMore,
        ]);
    }

    public function types(Request $request): JsonResponse
    {
        $fromStr = $request->query->get('from');
        $toStr = $request->query->get('to');
        $from = is_string($fromStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $fromStr) : null;
        $to = is_string($toStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $toStr) : null;
        $from = $from instanceof DateTimeImmutable ? $from : null;
        $to = $to instanceof DateTimeImmutable ? $to : null;

        $stats = $this->storage->findFlowTypeStats($from, $to);
        $groupMap = FlowDiscovery::discover();

        $result = [];
        foreach ($stats as $stat) {
            $item = $stat->jsonSerialize();
            $item['group'] = $groupMap[$stat->flowType] ?? null;
            $result[] = $item;
        }

        return new JsonResponse($result);
    }

    public function projections(): JsonResponse
    {
        $result = [];
        foreach (ProjectionDiscovery::discover() as $projectionHandlerMetum) {
            foreach ($projectionHandlerMetum->flowTypes as $flowType) {
                $result[$flowType] = [
                    'projectionHandlerClass' => $projectionHandlerMetum->handlerClass,
                    'projectionMessageMethods' => $projectionHandlerMetum->messageMethods,
                ];
            }
        }

        return new JsonResponse($result);
    }

    public function stats(Request $request): JsonResponse
    {
        $fromStr = $request->query->get('from');
        $toStr = $request->query->get('to');
        $from = is_string($fromStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $fromStr) : null;
        $to = is_string($toStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $toStr) : null;
        $from = $from instanceof DateTimeImmutable ? $from : null;
        $to = $to instanceof DateTimeImmutable ? $to : null;
        $type = $request->query->get('type');

        $stats = iterator_to_array($this->storage->findFlowStats($from, $to, $type));

        return new JsonResponse($stats);
    }

    public function search(Request $request): JsonResponse
    {
        $subject = Assert::string($request->query->get('subject') ?? '');

        if ($subject === '') {
            return new JsonResponse([
                'items' => [],
                'total' => 0,
                'hasMore' => false,
            ]);
        }

        $top = max(1, min(100, (int) $request->query->get('top', 10)));

        $flows = $this->storage->findFlowsBySubject($subject, SortEnum::DESC, $top + 1);
        $items = iterator_to_array($flows);
        $hasMore = count($items) > $top;
        if ($hasMore) {
            array_pop($items);
        }

        $total = $this->storage->countFlowsBySubject($subject);

        return new JsonResponse([
            'items' => $items,
            'total' => $total,
            'hasMore' => $hasMore,
        ]);
    }

    public function detail(Request $request): JsonResponse
    {
        $hash = Assert::string($request->query->get('hash') ?? '');
        $runtimeHash = Assert::string($request->query->get('runtimeHash') ?? '');

        if ($hash === '' && $runtimeHash === '') {
            return new JsonResponse([
                'error' => 'hash or runtimeHash parameter required',
            ], 400);
        }

        $ephemeralJson = $hash > ''
            ? $this->storage->findEphemeralFlowJson($hash)
            : $this->storage->findEphemeralFlowJsonByRuntimeHash($runtimeHash);

        if ($ephemeralJson !== null) {
            $decoded = json_decode($ephemeralJson, true);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Invalid ephemeral Flow JSON');
            }

            $decoded['isReadOnly'] = true;
            $decoded['isExecutable'] = false;
            $reasons = $decoded['readOnlyReasons'] ?? [];
            if (is_array($reasons)) {
                $reasons[] = 'ephemeral flow — no primary storage data';
                $decoded['readOnlyReasons'] = $reasons;
            }

            return new JsonResponse($decoded);
        }

        $flow = $hash > ''
            ? $this->storage->findFlowByHash($hash)
            : $this->storage->findFlowByRuntimeHash($runtimeHash);

        if (!$flow instanceof Flow) {
            return new JsonResponse([
                'error' => 'Flow not found',
            ], 404);
        }

        return new JsonResponse($flow);
    }

    public function run(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
            ], 400);
        }

        $flowHash = Assert::string($body['flowHash'] ?? '');
        $flowSource = Assert::string($body['flowSource'] ?? '');
        $flowSubject = Assert::nullOrString($body['flowSubject'] ?? null);
        $messageSource = Assert::string($body['messageSource'] ?? '');
        $message = Assert::array($body['message'] ?? []);
        /** @var class-string[] $includeSteps */
        $includeSteps = Assert::array($body['includeSteps'] ?? []);
        $messageReturn = null;

        $isEmptyInitMessage = is_a($messageSource, EmptyInitMessage::class, true);
        if ($messageSource === '' || ($message === [] && !$isEmptyInitMessage)) {
            return new JsonResponse([
                'error' => 'messageSource and message required',
            ], 400);
        }

        if ($flowHash === '' && $flowSource === '') {
            return new JsonResponse([
                'error' => 'flowHash or flowSource required',
            ], 400);
        }

        // Re-run an existing flow (flowHash) or start a fresh one (flowSource).
        $existingFlow = null;
        if ($flowHash !== '') {
            $existingFlow = $this->storage->findFlowByHash($flowHash);
            if (!$existingFlow instanceof Flow) {
                return new JsonResponse([
                    'error' => 'Flow not found',
                ], 404);
            }
        }

        try {
            $flowSource = $this->flowPreflight->ensureFlowSource(
                $existingFlow instanceof Flow ? $existingFlow->getSource() : $flowSource,
            );
            $messageSource = $this->flowPreflight->ensureMessageSource($flowSource, $messageSource);
            $messageInstance = $this->flowPreflight->hydrateMessage($messageSource, $message);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return new JsonResponse([
                'error' => $invalidArgumentException->getMessage(),
            ], 400);
        }

        if ($existingFlow instanceof Flow && !$existingFlow->isExecutable()) {
            return new JsonResponse([
                'error' => 'Flow is not executable',
            ], 400);
        }

        $flowType = $existingFlow instanceof Flow
            ? $existingFlow->getType()
            : $flowSource::schema()->type();
        $flowSubject = $existingFlow instanceof Flow
            ? $existingFlow->getSubject()
            : $flowSubject;

        try {
            $flowRunner = new FlowRunner(
                type: $flowType,
                flowSource: $flowSource,
                flowSubject: $flowSubject,
                storage: $this->storage,
                queue: $this->queue,
                dependencyRegistry: $this->flowcrafterConfig->getDependencyRegistry(),
                projectionHandlerMetas: ProjectionDiscovery::discover(),
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
                flowHash: $flowHash ?: null,
                includeSteps: $includeSteps,
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            return new JsonResponse([
                'error' => $invalidArgumentException->getMessage(),
            ], 400);
        } catch (Throwable) {
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
}
