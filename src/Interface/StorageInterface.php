<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

use DateTimeInterface;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;

interface StorageInterface
{
    public function initializeDatabase(): void;

    public function registerFlowSchema(FlowSchema $flowSchema): void;

    public function registerFlowInstance(Flow $flow): void;

    public function appendFlowRun(Flow $flow, ?string $queueId = null): void;

    public function appendFlowMessage(FlowMessage $flowMessage): void;

    public function appendFlowException(FlowException $flowException): void;

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     */
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, array $message): void;

    public function openQueues(): int;

    /**
     * @return iterable<ObserveItem>
     */
    public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable;

    /**
     * @return iterable<ObserveItem>
     */
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable;

    public function countFlows(): int;

    public function countFlowsBySource(string $flowSource = ''): int;

    public function countFlowsByType(string $flowType = ''): int;

    public function countExceptions(): int;

    public function countExceptionsByFlowHash(string $flowHash = ''): int;

    /**
     * @return FlowEntity[]
     */
    public function findAllFlows(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable;

    /**
     * @return FlowEntity[]
     */
    public function findFlowsBySource(string $flowSource, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable;

    /**
     * @return FlowEntity[]
     */
    public function findFlowsByType(string $flowType, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable;

    /**
     * @return FlowException[]
     */
    public function findAllExceptions(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable;

    /**
     * @return FlowException[]
     */
    public function findExceptionsByFlowHash(string $flowHash, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable;

    public function findFlowByHash(string $flowHash): ?Flow;

    public function findFlowByRuntimeHash(string $flowRuntimeHash): ?Flow;
}
