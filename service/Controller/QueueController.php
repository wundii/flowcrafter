<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\Interface\StorageInterface;

final class QueueController
{
    public function __construct(
        private readonly StorageInterface $storage,
    ) {
    }

    public function list(Request $request): JsonResponse
    {
        $sort = $request->query->get('sort', 'desc') === 'asc' ? SortEnum::ASC : SortEnum::DESC;

        $result = [];
        foreach ($this->storage->findAllQueues($sort) as $item) {
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
        $message = Assert::array($body['message'] ?? []);
        /** @var class-string[] $includeStubs */
        $includeStubs = Assert::array($body['includeStubs'] ?? []);
        $type = Assert::string($body['type'] ?? '');
        $flowSource = Assert::string($body['flowSource'] ?? '');
        $flowSubject = Assert::nullOrString($body['flowSubject'] ?? null);

        if ($flowHash !== null && $flowHash !== '') {
            $flow = $this->storage->findFlowByHash($flowHash);
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
            $this->storage->appendObserveItem(
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
            'subject' => $flowSubject,
        ]);
    }
}
