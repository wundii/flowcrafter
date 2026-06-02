<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Projection\ProjectionQueueItem;

interface QueueInterface
{
    /**
     * Prepare the queue backend (e.g. create tables/indices). May be a no-op
     * for backends that need no schema.
     */
    public function initializeQueue(): void;

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     * @param class-string[] $includeSteps
     */
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, ?array $message, array $includeSteps = [], ?string $flowSubject = null): void;

    public function appendProjectionQueueItem(FlowMessage $flowMessage): void;

    /**
     * @return iterable<ObserveItem>
     */
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable;

    public function openQueues(): int;

    /**
     * @return iterable<ObserveItem>
     */
    public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable;

    /**
     * @return iterable<ProjectionQueueItem>
     */
    public function observeProjectionQueue(float $maxExecutionTimeInSeconds = 0.0): iterable;

    public function ackProjectionQueueItem(string $itemId): void;
}
