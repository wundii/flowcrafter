<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\MockClass\BareMessageInterfaceMock;
use Tests\MockClass\MessageDataSecondMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\FlowPreflight;
use Wundii\Flowcrafter\Interface\MessageInterface;

final class FlowPreflightTest extends TestCase
{
    public function testEnsureFlowSourceAcceptsValidFlowClass(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->assertSame(WorkflowMock::class, $flowPreflight->ensureFlowSource(WorkflowMock::class));
    }

    public function testEnsureFlowSourceRejectsNonFlowClass(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $flowPreflight->ensureFlowSource(stdClass::class);
    }

    public function testEnsureFlowSourceRejectsUnknownClass(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $flowPreflight->ensureFlowSource('Not\\A\\Real\\Class');
    }

    public function testEnsureMessageSourceAcceptsValidMessageClass(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->assertSame(MessageInitMock::class, $flowPreflight->ensureMessageSource(MessageInitMock::class));
    }

    public function testEnsureMessageSourceRejectsNonMessageClass(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $flowPreflight->ensureMessageSource(stdClass::class);
    }

    public function testEnsureMessageSourceRejectsMessageInterfaceWithoutAbstractMessage(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $flowPreflight->ensureMessageSource(BareMessageInterfaceMock::class);
    }

    public function testHydrateMessageReturnsMessageInstanceForValidPayload(): void
    {
        $flowPreflight = new FlowPreflight();
        $message = $flowPreflight->hydrateMessage(MessageDataSecondMock::class, [
            'data' => 'hello',
            'messageSubDataMock' => [
                'value' => 'nested',
            ],
        ]);

        $this->assertInstanceOf(MessageInterface::class, $message);
        $this->assertInstanceOf(MessageDataSecondMock::class, $message);
        $this->assertSame('hello', $message->getData());
        $this->assertSame('nested', $message->getMessageSubDataMock()->getValue());
    }

    public function testHydrateMessageWithWrongPayload(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('message payload for "Tests\\MockClass\\MessageInitMock" is missing required keys: data. Unknown keys: data2');
        $flowPreflight->hydrateMessage(MessageInitMock::class, [
            'data2' => 'hello',
        ]);
    }

    public function testHydrateMessageWithIncompletePayload(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing required keys');
        $flowPreflight->hydrateMessage(MessageDataSecondMock::class, [
            'data' => 'hello',
        ]);
    }

    public function testHydrateMessageRejectsWithEmptyPayload(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing required keys');
        $flowPreflight->hydrateMessage(MessageDataSecondMock::class, []);
    }
}
