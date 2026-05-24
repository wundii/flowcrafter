<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowResult;
use Wundii\Flowcrafter\FlowRetry;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowSchemaEntity;
use Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity;
use Wundii\Flowcrafter\Storage\Entity\StepSourceEntity;
use Wundii\Flowcrafter\Storage\Service;

class NullStorageMock extends Service
{
    public function isPrimaryStorageInitialized(): bool
    {
        return true;
    }

    public function initializeDatabase(): void
    {
    }

    public function registerFlowSchema(FlowSchema $flowSchema): void
    {
    }

    public function registerMessageSource(MessageSourceEntity $messageSourceEntity): void
    {
    }

    public function registerStepSource(StepSourceEntity $stepSourceEntity): void
    {
    }

    public function registerFlowInstance(Flow $flow): void
    {
    }

    public function appendFlowRun(Flow $flow, ?string $queueId = null): void
    {
    }

    public function appendFlowMessage(FlowMessage $flowMessage): void
    {
    }

    public function appendFlowException(FlowException $flowException): void
    {
    }

    public function appendFlowResult(FlowResult $flowResult): void
    {
    }

    public function appendFlowRetry(FlowRetry $flowRetry): void
    {
    }

    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, ?array $message, array $includeSteps = [], ?string $flowSubject = null): void
    {
    }

    public function openQueues(): int
    {
        return 0;
    }

    /**
     * @return iterable<ObserveItem>
     */
    public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable
    {
        return [];
    }

    /**
     * @return iterable<string>
     */
    public function findAllFlowHashes(): iterable
    {
        return [];
    }

    /**
     * @return iterable<ObserveItem>
     */
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable
    {
        return [];
    }

    /**
     * @return iterable<FlowSchemaEntity>
     */
    public function findAllSchemas(): iterable
    {
        return [];
    }

    /**
     * @return iterable<MessageSourceEntity>
     */
    public function findAllMessageSources(): iterable
    {
        return [];
    }

    public function findFlowInstanceByHash(string $flowHash): ?FlowInstanceEntity
    {
        return null;
    }

    public function findFlowByHash(string $flowHash): ?Flow
    {
        return null;
    }

    public function findFlowByRuntimeHash(string $flowRuntimeHash): ?Flow
    {
        return null;
    }

    public function findStepSourceByHash(string $stepHash): ?StepSourceEntity
    {
        return null;
    }

    /**
     * @return iterable<StepSourceEntity>
     */
    public function findStepSourcesByStepSource(string $stepSource): iterable
    {
        return [];
    }

    /**
     * @return iterable<MessageSourceEntity>
     */
    public function findMessageSourceByMessageSource(string $messageSource): iterable
    {
        return [];
    }
}
