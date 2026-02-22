<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

final class FlowRunnerTest extends TestCase
{
    public function testRunReturnsMessageReturnInterface(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);

        $this->assertCount(5, $flow->getFlowMessages());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame('End of flow', $result->getData());
    }

    public function testRestartingAnWorkflow(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $result = $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);

        $this->assertCount(5, $flow->getFlowMessages());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame('End of flow', $result->getData());

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $this->assertInstanceOf(Flow::class, $flow);
        $result = $flowRunner->run(new MessageDataMock('test data round two'), $flow->getHash());

        $this->assertCount(4, $flowRunner->getFlow()->getFlowMessages());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame('End of flow', $result->getData());
    }
}
