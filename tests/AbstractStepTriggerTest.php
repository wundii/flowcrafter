<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\EnqueueTriggerStepMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\NullStorageMock;
use Tests\MockClass\WorkflowEnqueueTriggerMock;
use Tests\MockClass\WorkflowMock;
use Tests\MockClass\WorkflowRunTriggerMock;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Queue\InMemoryQueue;

final class AbstractStepTriggerTest extends TestCase
{
    public function testStepCanEnqueueFlow(): void
    {
        $inMemoryQueue = new InMemoryQueue();

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.enqueuetrigger.v1',
            flowSource: WorkflowEnqueueTriggerMock::class,
            storage: new NullStorageMock(),
            queue: $inMemoryQueue,
        );
        $result = $flowRunner->run(new MessageInitMock('payload'));

        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame('enqueued', $result->getData());

        $items = iterator_to_array($inMemoryQueue->findAllQueues());
        $this->assertCount(1, $items);

        $observeItem = $items[0];
        $this->assertInstanceOf(ObserveItem::class, $observeItem);
        $this->assertSame('flow.workflow.v1', $observeItem->getType());
        $this->assertSame(WorkflowMock::class, $observeItem->getFlowSource());
        $this->assertSame(MessageInitMock::class, $observeItem->getMessageSource());
        $this->assertSame('enqueued-subject', $observeItem->getFlowSubject());
        $this->assertSame([
            'data' => 'payload',
        ], $observeItem->getMessage());
    }

    public function testStepCanRunFlowSynchronously(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.runtrigger.v1',
            flowSource: WorkflowRunTriggerMock::class,
            storage: new NullStorageMock(),
            queue: new InMemoryQueue(),
        );
        $result = $flowRunner->run(new MessageInitMock('payload'));

        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertStringStartsWith('outer: [', $result->getData());
    }

    public function testEnqueueWithoutContextThrows(): void
    {
        $enqueueTriggerStepMock = new EnqueueTriggerStepMock(new MessageInitMock('payload'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Queue is not set.');

        $enqueueTriggerStepMock->process();
    }
}
