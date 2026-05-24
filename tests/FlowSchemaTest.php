<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageDataSecondMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageReturnMock;
use Tests\MockClass\NextStepMock;
use Tests\MockClass\OtherStepMock;
use Tests\MockClass\PostStepMock;
use Tests\MockClass\StepMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Source;
use Wundii\Flowcrafter\Step;

final class FlowSchemaTest extends TestCase
{
    public function testCreateFromFlowInterface(): void
    {
        $flowSchema = $this->createSchema();

        $this->assertInstanceOf(FlowSchema::class, $flowSchema);
        $this->assertSame('flow.workflow.v1', $flowSchema->type());
    }

    public function testCreateWithInvalidClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore argument.type */
        FlowSchema::create(MessageInitMock::class);
    }

    public function testType(): void
    {
        $flowSchema = $this->createSchema();

        $this->assertSame('flow.workflow.v1', $flowSchema->type());
    }

    public function testInitStep(): void
    {
        $flowSchema = $this->createSchema();
        $initStep = $flowSchema->initStep();

        $this->assertSame(StepMock::class, $initStep->getSource());
        $this->assertSame(MessageEnum::INIT, $initStep->getMessageEnum());
    }

    public function testStepsCount(): void
    {
        $flowSchema = $this->createSchema();

        $this->assertCount(4, $flowSchema->steps());
    }

    public function testStepByMessageClassFindsSteps(): void
    {
        $flowSchema = $this->createSchema();

        $steps = $flowSchema->stepByMessageClass(MessageDataMock::class);

        $this->assertCount(3, $steps);

        $sources = array_map(static fn (\Wundii\Flowcrafter\Step $step): string => $step->getSource(), $steps);
        $this->assertContains(NextStepMock::class, $sources);
        $this->assertContains(OtherStepMock::class, $sources);
        $this->assertContains(PostStepMock::class, $sources);
    }

    public function testStepByMessageClassWithInitMessage(): void
    {
        $flowSchema = $this->createSchema();

        $steps = $flowSchema->stepByMessageClass(MessageInitMock::class);

        $this->assertCount(1, $steps);
        $this->assertSame(StepMock::class, $steps[0]->getSource());
    }

    public function testStepByMessageClassReturnsEmptyForUnused(): void
    {
        $flowSchema = $this->createSchema();

        $steps = $flowSchema->stepByMessageClass(MessageReturnMock::class);

        $this->assertCount(0, $steps);
    }

    public function testStepByMessageClassWithInvalidClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $flowSchema = $this->createSchema();

        /** @phpstan-ignore argument.type */
        $flowSchema->stepByMessageClass(WorkflowMock::class);
    }

    public function testStepBySourceFindsStep(): void
    {
        $flowSchema = $this->createSchema();

        $step = $flowSchema->stepBySource(StepMock::class);

        $this->assertSame(StepMock::class, $step->getSource());
    }

    public function testStepBySourceFindsAllSteps(): void
    {
        $flowSchema = $this->createSchema();

        $this->assertSame(NextStepMock::class, $flowSchema->stepBySource(NextStepMock::class)->getSource());
        $this->assertSame(OtherStepMock::class, $flowSchema->stepBySource(OtherStepMock::class)->getSource());
        $this->assertSame(PostStepMock::class, $flowSchema->stepBySource(PostStepMock::class)->getSource());
    }

    public function testStepBySourceThrowsForUnknown(): void
    {
        $this->expectException(RuntimeException::class);

        $flowBuilder = new FlowBuilder(
            'flow.minimal.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );
        $flowBuilder->addStep(StepMock::class);
        $flowBuilder->addStep(PostStepMock::class);

        $flowSchema = $flowBuilder->build();

        $flowSchema->stepBySource(NextStepMock::class);
    }

    public function testGetMessageToStepsMap(): void
    {
        $flowSchema = $this->createSchema();

        $map = $flowSchema->getMessageToStepsMap();

        $this->assertArrayHasKey(MessageInitMock::class, $map);
        $this->assertArrayHasKey(MessageDataMock::class, $map);
        $this->assertArrayHasKey(MessageDataSecondMock::class, $map);

        $this->assertCount(1, $map[MessageInitMock::class]);
        $this->assertCount(3, $map[MessageDataMock::class]);
        $this->assertCount(1, $map[MessageDataSecondMock::class]);
    }

    public function testGetHashIsConsistent(): void
    {
        $flowSchema = $this->createSchema();
        $schema2 = $this->createSchema();

        $this->assertSame($flowSchema->getHash(), $schema2->getHash());
    }

    public function testGetHashDiffersForDifferentSchemas(): void
    {
        $flowSchema = $this->createSchema();

        $flowBuilder = new FlowBuilder(
            'flow.minimal.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );
        $flowBuilder->addStep(StepMock::class);
        $flowBuilder->addStep(PostStepMock::class);

        $schema2 = $flowBuilder->build();

        $this->assertNotSame($flowSchema->getHash(), $schema2->getHash());
    }

    public function testGetLeafSteps(): void
    {
        $flowSchema = $this->createSchema();

        $leafSteps = $flowSchema->getLeafSteps();
        $sources = array_map(static fn (\Wundii\Flowcrafter\Step $step): string => $step->getSource(), $leafSteps);

        $this->assertContains(NextStepMock::class, $sources);
        $this->assertContains(PostStepMock::class, $sources);
        $this->assertNotContains(StepMock::class, $sources);
        $this->assertNotContains(OtherStepMock::class, $sources);
    }

    public function testJsonSerialize(): void
    {
        $flowSchema = $this->createSchema();

        $data = $flowSchema->jsonSerialize();

        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('steps', $data);
        $this->assertSame('flow.workflow.v1', $data['type']);
        $this->assertCount(4, $data['steps']);
    }

    public function testStepJsonSerializeIncludesMessageHashes(): void
    {
        $step = Step::create(StepMock::class);
        $json = $step->jsonSerialize();

        $this->assertArrayHasKey('messageHashes', $json);
        $this->assertArrayHasKey(MessageInitMock::class, $json['messageHashes']);
        $this->assertArrayHasKey(MessageDataMock::class, $json['messageHashes']);

        $this->assertSame(
            Source::message(MessageInitMock::class)->messageHash,
            $json['messageHashes'][MessageInitMock::class],
        );
        $this->assertSame(
            Source::message(MessageDataMock::class)->messageHash,
            $json['messageHashes'][MessageDataMock::class],
        );
    }

    public function testSchemaHashIncludesMessageStructure(): void
    {
        $flowSchema = $this->createSchema();
        $flowSchema->getHash();

        $stepWithAlteredHash = new Step(
            source: StepMock::class,
            messages: [MessageInitMock::class],
            returnTypes: [MessageDataMock::class],
            messageEnum: MessageEnum::INIT,
            skipClassValidation: true,
        );

        $alteredJson = $stepWithAlteredHash->jsonSerialize();
        $this->assertArrayHasKey('messageHashes', $alteredJson);

        $originalStep = Step::create(StepMock::class);
        $this->assertSame(
            $originalStep->jsonSerialize()['messageHashes'],
            $alteredJson['messageHashes'],
        );
    }

    private function createSchema(): FlowSchema
    {
        return FlowSchema::create(WorkflowMock::class);
    }
}
