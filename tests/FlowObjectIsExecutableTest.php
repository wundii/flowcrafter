<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Flow;

final class FlowObjectIsExecutableTest extends TestCase
{
    public function testIsExecutableReturnsTrueWithMatchingSchemaHash(): void
    {
        $flow = Flow::create(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );

        $this->assertTrue($flow->isExecutable());
    }

    public function testIsExecutableReturnsFalseWithMismatchedSchemaHash(): void
    {
        $flow = Flow::create(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSchemaHash: 'outdated-schema-hash',
        );

        $this->assertFalse($flow->isExecutable());
    }

    public function testIsExecutableReflectedInJsonSerialize(): void
    {
        $executableFlow = Flow::create(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );

        $nonExecutableFlow = Flow::create(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSchemaHash: 'outdated-schema-hash',
        );

        $this->assertTrue($executableFlow->jsonSerialize()['isExecutable']);
        $this->assertFalse($nonExecutableFlow->jsonSerialize()['isExecutable']);
    }
}
