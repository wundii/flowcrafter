<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowResult;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Schedule\ScheduleException;
use Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowSchemaEntity;
use Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;

interface StorageInterface extends ServiceInterface
{
    public function initializeDatabase(): void;

    public function isPrimaryStorageInitialized(): bool;

    public function registerFlowSchema(FlowSchema $flowSchema): void;

    public function registerMessageSource(MessageSourceEntity $messageSourceEntity): void;

    public function registerStubSource(StubSourceEntity $stubSourceEntity): void;

    public function registerFlowInstance(Flow $flow): void;

    public function appendFlowRun(Flow $flow, ?string $queueId = null): void;

    public function appendFlowMessage(FlowMessage $flowMessage): void;

    public function appendFlowException(FlowException $flowException): void;

    public function appendScheduleException(ScheduleException $scheduleException): void;

    public function appendFlowResult(FlowResult $flowResult): void;

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     * @param class-string[] $includeStubs
     */
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, array $message, array $includeStubs = [], ?string $flowSubject = null): void;

    public function openQueues(): int;

    /**
     * @return iterable<ObserveItem>
     */
    public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable;

    /**
     * @return iterable<string>
     */
    public function findAllFlowHashes(): iterable;

    /**
     * @return iterable<ObserveItem>
     */
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable;

    /**
     * @return iterable<FlowSchemaEntity>
     */
    public function findAllSchemas(): iterable;

    /**
     * @return iterable<MessageSourceEntity>
     */
    public function findAllMessageSources(): iterable;

    public function findFlowInstanceByHash(string $flowHash): ?FlowInstanceEntity;

    public function findFlowByHash(string $flowHash): ?Flow;

    public function findFlowByRuntimeHash(string $flowRuntimeHash): ?Flow;

    public function findStubSourceByHash(string $stubHash): ?StubSourceEntity;

    /**
     * @param class-string $stubSource
     * @return iterable<StubSourceEntity>
     */
    public function findStubSourcesByStubSource(string $stubSource): iterable;

    /**
     * @param class-string $messageSource
     * @return iterable<MessageSourceEntity>
     */
    public function findMessageSourceByMessageSource(string $messageSource): iterable;
}
