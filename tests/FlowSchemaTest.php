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
use Tests\MockClass\NextStubMock;
use Tests\MockClass\OtherStubMock;
use Tests\MockClass\PostStubMock;
use Tests\MockClass\StubMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;

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

    public function testInitStub(): void
    {
        $flowSchema = $this->createSchema();
        $initStub = $flowSchema->initStub();

        $this->assertSame(StubMock::class, $initStub->getSource());
        $this->assertSame(MessageEnum::INIT, $initStub->getMessageEnum());
    }

    public function testStubsCount(): void
    {
        $flowSchema = $this->createSchema();

        $this->assertCount(4, $flowSchema->stubs());
    }

    public function testStubByMessageClassFindsStubs(): void
    {
        $flowSchema = $this->createSchema();

        $stubs = $flowSchema->stubByMessageClass(MessageDataMock::class);

        $this->assertCount(3, $stubs);

        $sources = array_map(static fn (\Wundii\Flowcrafter\Stub $stub): string => $stub->getSource(), $stubs);
        $this->assertContains(NextStubMock::class, $sources);
        $this->assertContains(OtherStubMock::class, $sources);
        $this->assertContains(PostStubMock::class, $sources);
    }

    public function testStubByMessageClassWithInitMessage(): void
    {
        $flowSchema = $this->createSchema();

        $stubs = $flowSchema->stubByMessageClass(MessageInitMock::class);

        $this->assertCount(1, $stubs);
        $this->assertSame(StubMock::class, $stubs[0]->getSource());
    }

    public function testStubByMessageClassReturnsEmptyForUnused(): void
    {
        $flowSchema = $this->createSchema();

        $stubs = $flowSchema->stubByMessageClass(MessageReturnMock::class);

        $this->assertCount(0, $stubs);
    }

    public function testStubByMessageClassWithInvalidClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $flowSchema = $this->createSchema();

        /** @phpstan-ignore argument.type */
        $flowSchema->stubByMessageClass(WorkflowMock::class);
    }

    public function testStubBySourceFindsStub(): void
    {
        $flowSchema = $this->createSchema();

        $stub = $flowSchema->stubBySource(StubMock::class);

        $this->assertSame(StubMock::class, $stub->getSource());
    }

    public function testStubBySourceFindsAllStubs(): void
    {
        $flowSchema = $this->createSchema();

        $this->assertSame(NextStubMock::class, $flowSchema->stubBySource(NextStubMock::class)->getSource());
        $this->assertSame(OtherStubMock::class, $flowSchema->stubBySource(OtherStubMock::class)->getSource());
        $this->assertSame(PostStubMock::class, $flowSchema->stubBySource(PostStubMock::class)->getSource());
    }

    public function testStubBySourceThrowsForUnknown(): void
    {
        $this->expectException(RuntimeException::class);

        $flowBuilder = new FlowBuilder(
            'flow.minimal.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );
        $flowBuilder->addStub(StubMock::class);
        $flowBuilder->addStub(PostStubMock::class);

        $flowSchema = $flowBuilder->build();

        $flowSchema->stubBySource(NextStubMock::class);
    }

    public function testGetMessageToSubsMap(): void
    {
        $flowSchema = $this->createSchema();

        $map = $flowSchema->getMessageToSubsMap();

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
        $flowBuilder->addStub(StubMock::class);
        $flowBuilder->addStub(PostStubMock::class);

        $schema2 = $flowBuilder->build();

        $this->assertNotSame($flowSchema->getHash(), $schema2->getHash());
    }

    public function testGetLeafStubs(): void
    {
        $flowSchema = $this->createSchema();

        $leafStubs = $flowSchema->getLeafStubs();
        $sources = array_map(static fn (\Wundii\Flowcrafter\Stub $stub): string => $stub->getSource(), $leafStubs);

        $this->assertContains(NextStubMock::class, $sources);
        $this->assertContains(PostStubMock::class, $sources);
        $this->assertNotContains(StubMock::class, $sources);
        $this->assertNotContains(OtherStubMock::class, $sources);
    }

    public function testJsonSerialize(): void
    {
        $flowSchema = $this->createSchema();

        $data = $flowSchema->jsonSerialize();

        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('stubs', $data);
        $this->assertSame('flow.workflow.v1', $data['type']);
        $this->assertCount(4, $data['stubs']);
    }

    private function createSchema(): FlowSchema
    {
        return FlowSchema::create(WorkflowMock::class);
    }
}
