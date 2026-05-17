<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\BoolStepMock;
use Tests\MockClass\DanglingStepMock;
use Tests\MockClass\DisconnectedStepMock;
use Tests\MockClass\LoopStepAlphaMock;
use Tests\MockClass\LoopStepBetaMock;
use Tests\MockClass\LoopStepGammaMock;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageReturnMock;
use Tests\MockClass\NextStepMock;
use Tests\MockClass\OtherStepMock;
use Tests\MockClass\PostStepMock;
use Tests\MockClass\StepMock;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\FlowBuilder;

final class FlowBuilderTest extends TestCase
{
    public function testConstructorWithInvalidMessageInit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FlowBuilder(
            'flow.test.v1',
            MessageDataMock::class,
            MessageReturnMock::class,
        );
    }

    public function testConstructorWithInvalidMessageReturn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageDataMock::class,
        );
    }

    public function testAddStepWithInvalidClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        /** @phpstan-ignore argument.type */
        $flowBuilder->addStep(MessageInitMock::class);
    }

    public function testBuildWithoutSteps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MessageInit');

        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->build();
    }

    public function testBuildMissingMessageReturn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MessageReturn');

        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(StepMock::class);

        $flowBuilder->build();
    }

    public function testBuildSuccess(): void
    {
        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(StepMock::class);
        $flowBuilder->addStep(NextStepMock::class);
        $flowBuilder->addStep(OtherStepMock::class);
        $flowBuilder->addStep(PostStepMock::class);

        $flowSchema = $flowBuilder->build();

        $this->assertSame('flow.test.v1', $flowSchema->type());
        $this->assertCount(4, $flowSchema->steps());
    }

    public function testBuildReturnsCorrectSteps(): void
    {
        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(StepMock::class);
        $flowBuilder->addStep(NextStepMock::class);
        $flowBuilder->addStep(OtherStepMock::class);
        $flowBuilder->addStep(PostStepMock::class);

        $flowSchema = $flowBuilder->build();
        $steps = $flowSchema->steps();

        $this->assertSame(StepMock::class, $steps[0]->getSource());
        $this->assertSame(NextStepMock::class, $steps[1]->getSource());
        $this->assertSame(OtherStepMock::class, $steps[2]->getSource());
        $this->assertSame(PostStepMock::class, $steps[3]->getSource());
    }

    public function testBuildWithoutMessageReturn(): void
    {
        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
        );

        $flowBuilder->addStep(BoolStepMock::class);

        $flowSchema = $flowBuilder->build();

        $this->assertSame('flow.test.v1', $flowSchema->type());
        $this->assertCount(1, $flowSchema->steps());
    }

    public function testBuildMissingMessageReturnStillValidatesWhenProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MessageReturn');

        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(BoolStepMock::class);
        $flowBuilder->build();
    }

    public function testBuildInitStepIsDetected(): void
    {
        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(StepMock::class);
        $flowBuilder->addStep(NextStepMock::class);
        $flowBuilder->addStep(OtherStepMock::class);
        $flowBuilder->addStep(PostStepMock::class);

        $flowSchema = $flowBuilder->build();
        $initStep = $flowSchema->initStep();

        $this->assertSame(StepMock::class, $initStep->getSource());
        $this->assertSame(MessageEnum::INIT, $initStep->getMessageEnum());
    }

    public function testBuildDetectsLoop(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Loop detected in step chain');

        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
        );

        $flowBuilder->addStep(LoopStepAlphaMock::class);
        $flowBuilder->addStep(LoopStepBetaMock::class);
        $flowBuilder->addStep(LoopStepGammaMock::class);

        $flowBuilder->build();
    }

    public function testBuildDetectsDisconnectedStep(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not connected to the flow');

        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        // StepMock(InitMock) -> DataMock -> NextStepMock (leaf, bool)
        // DisconnectedStepMock(DataSecondMock) -> ReturnMock — not reachable from init
        $flowBuilder->addStep(StepMock::class);
        $flowBuilder->addStep(NextStepMock::class);
        $flowBuilder->addStep(DisconnectedStepMock::class);

        $flowBuilder->build();
    }

    public function testAddDuplicateStepThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already added');

        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
        );

        $flowBuilder->addStep(StepMock::class);
        $flowBuilder->addStep(StepMock::class);
    }

    public function testConstructorWithInvalidTypeFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must start with "flow."');

        new FlowBuilder(
            'invalid-type',
            MessageInitMock::class,
        );
    }

    public function testConstructorWithMissingVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must start with "flow."');

        new FlowBuilder(
            'flow.test',
            MessageInitMock::class,
        );
    }

    public function testBuildDetectsDanglingReturnType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not consumed by any step');

        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
        );

        // DanglingStepMock(InitMock) -> DataMock, but no step consumes DataMock
        $flowBuilder->addStep(DanglingStepMock::class);

        $flowBuilder->build();
    }

    public function testAddStepWithRetryConfig(): void
    {
        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(StepMock::class, retries: 3, delay: 500);
        $flowBuilder->addStep(NextStepMock::class);
        $flowBuilder->addStep(OtherStepMock::class);
        $flowBuilder->addStep(PostStepMock::class);

        $flowSchema = $flowBuilder->build();
        $steps = $flowSchema->steps();

        $this->assertSame(3, $steps[0]->getRetries());
        $this->assertSame(500, $steps[0]->getDelay());
        $this->assertSame(0, $steps[1]->getRetries());
        $this->assertSame(200, $steps[1]->getDelay());
    }

    public function testRetryConfigDoesNotAffectSchemaHash(): void
    {
        $flowBuilder1 = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );
        $flowBuilder1->addStep(StepMock::class);
        $flowBuilder1->addStep(NextStepMock::class);
        $flowBuilder1->addStep(OtherStepMock::class);
        $flowBuilder1->addStep(PostStepMock::class);

        $flowBuilder2 = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );
        $flowBuilder2->addStep(StepMock::class, retries: 5, delay: 1000);
        $flowBuilder2->addStep(NextStepMock::class);
        $flowBuilder2->addStep(OtherStepMock::class);
        $flowBuilder2->addStep(PostStepMock::class);

        $this->assertSame(
            $flowBuilder1->build()->getHash(),
            $flowBuilder2->build()->getHash(),
        );
    }
}
