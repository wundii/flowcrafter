<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Queue;

use DateTimeImmutable;
use RuntimeException;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowMessageReadonly;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Projection\ProjectionQueueItem;
use Wundii\Flowcrafter\Uuid;

final class InMemoryQueue implements QueueInterface
{
    /**
     * @var ObserveItem[]
     */
    private array $observeItems = [];

    /**
     * @var array<string, ProjectionQueueItem>
     */
    private array $projectionItems = [];

    public function initializeQueue(): void
    {
        // no-op: arrays need no schema setup
    }

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     * @param class-string[] $includeSteps
     */
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, ?array $message, array $includeSteps = [], ?string $flowSubject = null): void
    {
        Assert::classString($flowSource, FlowInterface::class);
        Assert::classString($messageSource, MessageInterface::class);

        /** @var class-string<FlowInterface> $flowSource */
        $this->observeItems[] = new ObserveItem(
            queueId: Uuid::uuid7(new DateTimeImmutable())->toString(),
            type: $type,
            flowSubject: $flowSubject,
            flowSource: $flowSource,
            flowHash: $flowHash,
            messageSource: $messageSource,
            message: $message,
            includeSteps: $includeSteps,
        );
    }

    /**
     * @return iterable<ObserveItem>
     */
    public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable
    {
        while ($this->observeItems !== []) {
            yield array_shift($this->observeItems);
        }
    }

    public function openQueues(): int
    {
        return count($this->observeItems);
    }

    /**
     * @return iterable<ObserveItem>
     */
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable
    {
        $items = $this->observeItems;

        if ($sortEnum === SortEnum::DESC) {
            $items = array_reverse($items);
        }

        yield from $items;
    }

    public function appendProjectionQueueItem(FlowMessage $flowMessage): void
    {
        $payloadJson = json_encode($flowMessage);
        if (!is_string($payloadJson)) {
            throw new RuntimeException('Could not serialize projection queue payload.');
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Could not deserialize projection queue payload.');
        }

        /** @var array<string, mixed> $payload */
        $itemId = Uuid::uuid7(new DateTimeImmutable())->toString();
        $this->projectionItems[$itemId] = new ProjectionQueueItem(
            itemId: $itemId,
            flowMessageReadonly: FlowMessageReadonly::createFromArray($payload),
        );
    }

    /**
     * @return iterable<ProjectionQueueItem>
     */
    public function observeProjectionQueue(float $maxExecutionTimeInSeconds = 0.0): iterable
    {
        foreach (array_keys($this->projectionItems) as $itemId) {
            // re-check: a consumer may ack (and thereby remove) items mid-drain
            if (array_key_exists($itemId, $this->projectionItems)) {
                yield $this->projectionItems[$itemId];
            }
        }
    }

    public function ackProjectionQueueItem(string $itemId): void
    {
        unset($this->projectionItems[$itemId]);
    }
}
