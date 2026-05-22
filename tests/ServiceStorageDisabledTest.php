<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Schedule\ScheduleException;
use Wundii\Flowcrafter\Storage\Service;

final class ServiceStorageDisabledTest extends TestCase
{
    public function testServiceMethodsReturnEmptyWhenDisabled(): void
    {
        $service = $this->createDisabledService();

        $this->assertFalse($service->isServiceStorageInitialized());
        $this->assertSame(0, $service->countFlows());
        $this->assertSame(0, $service->countExceptions());
        $this->assertSame(0, $service->countScheduleExceptions());
        $this->assertSame(0, $service->countObserverExceptions());
        $this->assertSame(0, $service->countFlowsByType('flow.test'));
        $this->assertSame(0, $service->countFlowsBySource('TestFlow'));
        $this->assertSame(0, $service->countFlowsBySubject('test'));
        $this->assertSame([], iterator_to_array($service->findAllFlows()));
        $this->assertSame([], iterator_to_array($service->findFlowsByType('flow.test')));
        $this->assertSame([], iterator_to_array($service->findFlowsBySource('TestFlow')));
        $this->assertSame([], iterator_to_array($service->findFlowsBySubject('test')));
        $this->assertSame([], iterator_to_array($service->findFlowStats()));
        $this->assertSame([], iterator_to_array($service->findFlowTypeStats()));
        $this->assertSame([], iterator_to_array($service->findAllExceptions()));
        $this->assertSame([], iterator_to_array($service->findExceptionStats()));
    }

    public function testAppendFlowNoOpWhenDisabled(): void
    {
        $service = $this->createDisabledService();

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $service,
        );
        $result = $flowRunner->run(new MessageInitMock('test'));

        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame(0, $service->countFlows());
    }

    public function testAppendScheduleExceptionNoOpWhenDisabled(): void
    {
        $service = $this->createDisabledService();

        $scheduleException = ScheduleException::create('TestSchedule', 'test', '* * * * *', 0, 'fail', __FILE__, __LINE__, '');
        $service->appendScheduleException($scheduleException);

        $this->assertSame(0, $service->countScheduleExceptions());
    }

    public function testTruncateFlowListNoOpWhenDisabled(): void
    {
        $service = $this->createDisabledService();

        $service->truncateFlowList();
        $this->assertSame(0, $service->countFlows());
    }

    public function testFlowRunnerWithoutStorageStillWorks(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(new MessageInitMock('no storage'));

        $this->assertInstanceOf(MessageReturnInterface::class, $result);

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
    }

    public function testFlowRunnerFailWithoutStorageDoesNotCrash(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: $this->createDisabledService(),
        );

        try {
            $flowRunner->run(new MessageInitMock('test'));
            $this->fail('Expected exception');
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }
    }

    private function createDisabledService(): Service
    {
        return new class() extends Service {
            public function isPrimaryStorageInitialized(): bool
            {
                return true;
            }

            public function initializeDatabase(): void
            {
            }

            public function registerFlowSchema(\Wundii\Flowcrafter\FlowSchema $flowSchema): void
            {
            }

            public function registerMessageSource(\Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity $messageSourceEntity): void
            {
            }

            public function registerStepSource(\Wundii\Flowcrafter\Storage\Entity\StepSourceEntity $stepSourceEntity): void
            {
            }

            public function registerFlowInstance(Flow $flow): void
            {
            }

            public function appendFlowRun(Flow $flow, ?string $queueId = null): void
            {
            }

            public function appendFlowMessage(\Wundii\Flowcrafter\FlowMessage $flowMessage): void
            {
            }

            public function appendFlowException(\Wundii\Flowcrafter\FlowException $flowException): void
            {
            }

            public function appendFlowResult(\Wundii\Flowcrafter\FlowResult $flowResult): void
            {
            }

            public function appendFlowRetry(\Wundii\Flowcrafter\FlowRetry $flowRetry): void
            {
            }

            public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, ?array $message, array $includeSteps = [], ?string $flowSubject = null): void
            {
            }

            public function openQueues(): int
            {
                return 0;
            }

            public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable
            {
                return [];
            }

            public function findAllFlowHashes(): iterable
            {
                return [];
            }

            public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable
            {
                return [];
            }

            public function findAllSchemas(): iterable
            {
                return [];
            }

            public function findAllMessageSources(): iterable
            {
                return [];
            }

            public function findFlowInstanceByHash(string $flowHash): ?\Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity
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

            public function findStepSourceByHash(string $stepHash): ?\Wundii\Flowcrafter\Storage\Entity\StepSourceEntity
            {
                return null;
            }

            public function findStepSourcesByStepSource(string $stepSource): iterable
            {
                return [];
            }

            public function findMessageSourceByMessageSource(string $messageSource): iterable
            {
                return [];
            }
        };
    }
}
