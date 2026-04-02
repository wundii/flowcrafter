<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageReturnMock;
use Tests\MockClass\NextStubMock;
use Tests\MockClass\OtherStubMock;
use Tests\MockClass\PostStubMock;
use Tests\MockClass\StubMock;
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

    public function testAddStubWithInvalidClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        /** @phpstan-ignore argument.type */
        $flowBuilder->addStub(MessageInitMock::class);
    }

    public function testBuildWithoutStubs(): void
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

        $flowBuilder->addStub(StubMock::class);

        $flowBuilder->build();
    }

    public function testBuildSuccess(): void
    {
        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStub(StubMock::class);
        $flowBuilder->addStub(NextStubMock::class);
        $flowBuilder->addStub(OtherStubMock::class);
        $flowBuilder->addStub(PostStubMock::class);

        $flowSchema = $flowBuilder->build();

        $this->assertSame('flow.test.v1', $flowSchema->type());
        $this->assertCount(4, $flowSchema->stubs());
    }

    public function testBuildReturnsCorrectStubs(): void
    {
        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStub(StubMock::class);
        $flowBuilder->addStub(NextStubMock::class);
        $flowBuilder->addStub(OtherStubMock::class);
        $flowBuilder->addStub(PostStubMock::class);

        $flowSchema = $flowBuilder->build();
        $stubs = $flowSchema->stubs();

        $this->assertSame(StubMock::class, $stubs[0]->getSource());
        $this->assertSame(NextStubMock::class, $stubs[1]->getSource());
        $this->assertSame(OtherStubMock::class, $stubs[2]->getSource());
        $this->assertSame(PostStubMock::class, $stubs[3]->getSource());
    }

    public function testBuildInitStubIsDetected(): void
    {
        $flowBuilder = new FlowBuilder(
            'flow.test.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStub(StubMock::class);
        $flowBuilder->addStub(NextStubMock::class);
        $flowBuilder->addStub(OtherStubMock::class);
        $flowBuilder->addStub(PostStubMock::class);

        $flowSchema = $flowBuilder->build();
        $initStub = $flowSchema->initStub();

        $this->assertSame(StubMock::class, $initStub->getSource());
        $this->assertSame(MessageEnum::INIT, $initStub->getMessageEnum());
    }
}
