<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

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

    /**
     * @return iterable<ObserveItem>
     */
    public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable;

    /**
     * @return FlowEntity[]
     */
    public function findAllFlows(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000): iterable;

    /**
     * @return FlowEntity[]
     */
    public function findFlowsBySource(string $flowSource, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000): iterable;

    public function findFlowByHash(string $flowHash): ?Flow;

    public function findFlowByRuntimeHash(string $flowRuntimeHash): ?Flow;
}
