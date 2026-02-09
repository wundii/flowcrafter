<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

final class FlowRunnerTest extends TestCase
{
    public function testRunReturnsMessageReturnInterface(): void
    {
        $flowRunner = new FlowRunner(
            'flow.workflow.v1',
            WorkflowMock::class,
        );
        $result = $flowRunner->run(new MessageInitMock('test data'));

        $this->assertCount(5, $flowRunner->getFlowMessages());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame('End of flow', $result->getData());
    }
}
