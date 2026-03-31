<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

use DateTimeInterface;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowResult;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;

interface StorageInterface
{
    public function initializeDatabase(): void;

    public function registerFlowSchema(FlowSchema $flowSchema): void;

    public function registerStubSource(StubSourceEntity $stubSourceEntity): void;

    public function registerFlowInstance(Flow $flow): void;

    public function appendFlowRun(Flow $flow, ?string $queueId = null): void;

    public function appendFlowMessage(FlowMessage $flowMessage): void;

    public function appendFlowException(FlowException $flowException): void;

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

    public function countExceptions(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int;

    public function countExceptionsByFlowHash(string $flowHash): int;

    /**
     * @return iterable<string>
     */
    public function findAllFlowHashes(): iterable;

    /**
     * @return iterable<array<mixed>>
     */
    public function findAllSchemas(): iterable;

    /**
     * @return iterable<ObserveItem>
     */
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable;

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

    public function findStubSourceByHash(string $stubHash): ?StubSourceEntity;

    /**
     * @param class-string $stubSource
     * @return iterable<StubSourceEntity>
     */
    public function findStubSourcesByStubSource(string $stubSource): iterable;
}
