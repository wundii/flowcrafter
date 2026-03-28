<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\FlowRunner;

final class FlowObjectTypeTest extends TestCase
{
    public static function validFlowTypeProvider(): \Iterator
    {
        yield 'simple' => ['flow.workflow.v1'];
        yield 'version 2' => ['flow.workflow.v2'];
        yield 'high version' => ['flow.workflow.v100'];
        yield 'nested name' => ['flow.order.process.v1'];
        yield 'deep nested' => ['flow.a.b.c.d.v3'];
    }

    public static function invalidFlowTypeProvider(): \Iterator
    {
        yield 'missing flow prefix' => ['workflow.v1'];
        yield 'missing version' => ['flow.workflow'];
        yield 'missing version number' => ['flow.workflow.v'];
        yield 'version without v' => ['flow.workflow.1'];
        yield 'empty string' => [''];
        yield 'only flow prefix' => ['flow.'];
        yield 'no dots' => ['flowworkflowv1'];
        yield 'trailing dot' => ['flow.workflow.v1.'];
        yield 'leading dot' => ['.flow.workflow.v1'];
        yield 'uppercase FLOW' => ['FLOW.workflow.v1'];
        yield 'version with text' => ['flow.workflow.vabc'];
    }

    #[DataProvider('validFlowTypeProvider')]
    public function testValidFlowTypeDoesNotThrow(string $flowType): void
    {
        $flowRunner = new FlowRunner(
            type: $flowType,
            flowSource: WorkflowMock::class,
        );

        try {
            $flowRunner->run(new MessageInitMock('test'));
        } catch (InvalidArgumentException $invalidArgumentException) {
            // schema type mismatch is expected for types != 'flow.workflow.v1'
            $this->assertStringNotContainsString('must start with "flow."', $invalidArgumentException->getMessage());

            return;
        }

        $this->assertTrue(true);
    }

    #[DataProvider('invalidFlowTypeProvider')]
    public function testInvalidFlowTypeThrowsException(string $flowType): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must start with "flow." and end with ".v" followed by a number');

        $flowRunner = new FlowRunner(
            type: $flowType,
            flowSource: WorkflowMock::class,
        );
        $flowRunner->run(new MessageInitMock('test'));
    }
}
