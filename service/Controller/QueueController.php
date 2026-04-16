<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\FlowPreflight;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity;

final class QueueController
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly FlowPreflight $flowPreflight,
    ) {
    }

    public function list(Request $request): JsonResponse
    {
        $sort = $request->query->get('sort', 'desc') === 'asc' ? SortEnum::ASC : SortEnum::DESC;

        $queueItems = $this->storage->findAllQueues($sort);

        return new JsonResponse(iterator_to_array($queueItems));
    }

    public function count(): JsonResponse
    {
        return new JsonResponse([
            'count' => $this->storage->openQueues(),
        ]);
    }

    public function enqueue(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
            ], 400);
        }

        $flowHash = Assert::nullOrString($body['flowHash'] ?? null);
        $messageSource = Assert::string($body['messageSource'] ?? '');
        $message = Assert::nullOrArray($body['message'] ?? null);
        /** @var class-string[] $includeStubs */
        $includeStubs = Assert::array($body['includeStubs'] ?? []);
        $flowType = Assert::string($body['type'] ?? '');
        $flowSource = Assert::string($body['flowSource'] ?? '');
        $flowSubject = Assert::nullOrString($body['flowSubject'] ?? null);

        if ($flowHash !== null && $flowHash !== '') {
            $flowInstance = $this->storage->findFlowInstanceByHash($flowHash);
            if (!$flowInstance instanceof FlowInstanceEntity) {
                return new JsonResponse([
                    'error' => 'Flow not found',
                ], 404);
            }

            $flowType = $flowInstance->flowType;
            $flowSource = $flowInstance->flowSource;
        }

        $isEmptyInitMessage = class_exists($messageSource) && is_a($messageSource, EmptyInitMessage::class, true);
        if ($flowType === '' || $flowSource === '' || $messageSource === '' || $message === null || ($message === [] && !$isEmptyInitMessage)) {
            return new JsonResponse([
                'error' => 'type, flowSource, messageSource and message required',
            ], 400);
        }

        try {
            $flowSource = $this->flowPreflight->ensureFlowSource($flowSource);
            $messageSource = $this->flowPreflight->ensureMessageSource($flowSource, $messageSource);
            $this->flowPreflight->hydrateMessage($messageSource, $message);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return new JsonResponse([
                'error' => $invalidArgumentException->getMessage(),
            ], 400);
        }

        try {
            $this->storage->appendObserveItem(
                type: $flowType,
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
            'subject' => $flowSubject,
        ]);
    }
}
