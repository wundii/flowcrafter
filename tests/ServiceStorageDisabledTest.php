<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\NullStorageMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Schedule\ScheduleException;

final class ServiceStorageDisabledTest extends TestCase
{
    public function testServiceMethodsReturnEmptyWhenDisabled(): void
    {
        $nullStorageMock = new NullStorageMock();

        $this->assertFalse($nullStorageMock->isServiceStorageInitialized());
        $this->assertSame(0, $nullStorageMock->countFlows());
        $this->assertSame(0, $nullStorageMock->countExceptions());
        $this->assertSame(0, $nullStorageMock->countScheduleExceptions());
        $this->assertSame(0, $nullStorageMock->countObserverExceptions());
        $this->assertSame(0, $nullStorageMock->countFlowsByType('flow.test'));
        $this->assertSame(0, $nullStorageMock->countFlowsBySource('TestFlow'));
        $this->assertSame(0, $nullStorageMock->countFlowsBySubject('test'));
        $this->assertSame([], iterator_to_array($nullStorageMock->findAllFlows()));
        $this->assertSame([], iterator_to_array($nullStorageMock->findFlowsByType('flow.test')));
        $this->assertSame([], iterator_to_array($nullStorageMock->findFlowsBySource('TestFlow')));
        $this->assertSame([], iterator_to_array($nullStorageMock->findFlowsBySubject('test')));
        $this->assertSame([], iterator_to_array($nullStorageMock->findFlowStats()));
        $this->assertSame([], iterator_to_array($nullStorageMock->findFlowTypeStats()));
        $this->assertSame([], iterator_to_array($nullStorageMock->findAllExceptions()));
        $this->assertSame([], iterator_to_array($nullStorageMock->findExceptionStats()));
    }

    public function testAppendFlowNoOpWhenDisabled(): void
    {
        $nullStorageMock = new NullStorageMock();

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $nullStorageMock,
        );
        $result = $flowRunner->run(new MessageInitMock('test'));

        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame(0, $nullStorageMock->countFlows());
    }

    public function testAppendScheduleExceptionNoOpWhenDisabled(): void
    {
        $nullStorageMock = new NullStorageMock();

        $scheduleException = ScheduleException::create('TestSchedule', 'test', '* * * * *', 0, 'fail', __FILE__, __LINE__, '');
        $nullStorageMock->appendScheduleException($scheduleException);

        $this->assertSame(0, $nullStorageMock->countScheduleExceptions());
    }

    public function testTruncateFlowListNoOpWhenDisabled(): void
    {
        $nullStorageMock = new NullStorageMock();

        $nullStorageMock->truncateFlowList();
        $this->assertSame(0, $nullStorageMock->countFlows());
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

    public function testFlowRunnerFailWithDisabledStorageDoesNotCrash(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: new NullStorageMock(),
        );

        try {
            $flowRunner->run(new MessageInitMock('test'));
            $this->fail('Expected exception');
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }
    }
}
