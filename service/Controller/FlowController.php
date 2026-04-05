<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Wundii\DataMapper\DataConfig;
use Wundii\DataMapper\DataMapper;
use Wundii\DataMapper\Enum\ApproachEnum;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\Entity\FlowListEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowStatsEntity;

final class FlowController
{
    public function __construct(
        private readonly FlowcrafterConfig $flowcrafterConfig,
        private readonly StorageInterface $storage,
    ) {
    }

    public function list(Request $request): JsonResponse
    {
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
            ? $this->storage->findFlowsByType($type, $sort, $top + 1, $skip, $from, $to)
            : $this->storage->findAllFlows($sort, $top + 1, $skip, $from, $to);

        $items = array_map($this->serializeEntity(...), iterator_to_array($flows));
        $hasMore = count($items) > $top;
        if ($hasMore) {
            array_pop($items);
        }

        $total = $type !== null
            ? $this->storage->countFlowsByType($type)
            : $this->storage->countFlows();

        return new JsonResponse([
            'items' => $items,
            'total' => $total,
            'hasMore' => $hasMore,
        ]);
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

        return new JsonResponse(array_map(
            static fn (FlowStatsEntity $flowStatsEntity): array => [
                'date' => $flowStatsEntity->date,
                'instances' => $flowStatsEntity->instances,
                'runs' => $flowStatsEntity->runs,
            ],
            $stats,
        ));
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
        $items = array_map($this->serializeEntity(...), iterator_to_array($flows));
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

        $existingFlow = $this->storage->findFlowByHash($flowHash);
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
                storage: $this->storage,
                dependenciesInjection: $this->flowcrafterConfig->getDependencyInjections(),
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

    /**
     * @return array<string, mixed>
     */
    private function serializeEntity(FlowListEntity $flowListEntity): array
    {
        return [
            'flowHash' => $flowListEntity->flowHash,
            'flowType' => $flowListEntity->flowType,
            'flowSource' => $flowListEntity->flowSource,
            'flowSubject' => $flowListEntity->flowSubject,
            'flowTime' => $flowListEntity->flowTime->format(DateTimeInterface::RFC3339_EXTENDED),
            'lastTerm' => $flowListEntity->lastTerm->format(DateTimeInterface::RFC3339_EXTENDED),
            'status' => $flowListEntity->statusEnum->name,
        ];
    }
}
