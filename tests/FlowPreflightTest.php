<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\MockClass\BareMessageInterfaceMock;
use Tests\MockClass\MessageDataSecondMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageSubDataMock;
use Tests\MockClass\MessageWithOptionalMock;
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
        $this->assertSame(
            MessageInitMock::class,
            $flowPreflight->ensureMessageSource(WorkflowMock::class, MessageInitMock::class),
        );
    }

    public function testEnsureMessageSourceRejectsNonMessageClass(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $flowPreflight->ensureMessageSource(WorkflowMock::class, stdClass::class);
    }

    public function testEnsureMessageSourceRejectsMessageInterfaceWithoutAbstractMessage(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $flowPreflight->ensureMessageSource(WorkflowMock::class, BareMessageInterfaceMock::class);
    }

    public function testEnsureMessageSourceRejectsMessageNotConsumedByFlow(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not consumed by any step');
        $flowPreflight->ensureMessageSource(WorkflowMock::class, MessageSubDataMock::class);
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

    public function testHydrateMessageUsesDefaultsForMissingOptionalKeys(): void
    {
        $flowPreflight = new FlowPreflight();
        $message = $flowPreflight->hydrateMessage(MessageWithOptionalMock::class, [
            'command' => 'off',
            'nullableRequired' => null,
        ]);

        $this->assertInstanceOf(MessageWithOptionalMock::class, $message);
        $this->assertSame('off', $message->getCommand());
        $this->assertNull($message->getNullableRequired());
        $this->assertNull($message->getLevel());
        $this->assertSame(60, $message->getSeconds());
    }

    public function testHydrateMessageTakesGivenOptionalKeys(): void
    {
        $flowPreflight = new FlowPreflight();
        $message = $flowPreflight->hydrateMessage(MessageWithOptionalMock::class, [
            'command' => 'on',
            'nullableRequired' => 'x',
            'level' => 'low',
            'seconds' => 5,
        ]);

        $this->assertInstanceOf(MessageWithOptionalMock::class, $message);
        $this->assertSame('x', $message->getNullableRequired());
        $this->assertSame('low', $message->getLevel());
        $this->assertSame(5, $message->getSeconds());
    }

    public function testHydrateMessageStillRequiresNullableKeysWithoutDefault(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is missing required keys: nullableRequired. Unknown keys: .');
        $flowPreflight->hydrateMessage(MessageWithOptionalMock::class, [
            'command' => 'off',
        ]);
    }

    public function testHydrateMessageReportsOnlyRequiredKeysAsMissing(): void
    {
        $flowPreflight = new FlowPreflight();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is missing required keys: command, nullableRequired. Unknown keys: .');
        $flowPreflight->hydrateMessage(MessageWithOptionalMock::class, []);
    }
}
