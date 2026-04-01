<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageDataSecondMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageReturnMock;
use Tests\MockClass\MessageSubDataMock;
use Wundii\Flowcrafter\Source;

final class AbstractMessageTest extends TestCase
{
    // jsonSerialize

    public function testJsonSerializeFlatMessage(): void
    {
        $messageDataMock = new MessageDataMock('hello');

        $this->assertSame([
            'data' => 'hello',
        ], $messageDataMock->jsonSerialize());
    }

    public function testJsonSerializeNestedMessage(): void
    {
        $messageSubDataMock = new MessageSubDataMock('world');
        $messageDataSecondMock = new MessageDataSecondMock('hello', $messageSubDataMock);

        $this->assertSame([
            'data' => 'hello',
            'messageSubDataMock' => $messageSubDataMock,
        ], $messageDataSecondMock->jsonSerialize());
    }

    public function testJsonSerializeWithNullableProperty(): void
    {
        $messageReturnMock = new MessageReturnMock('done');

        $this->assertSame([
            'data' => 'done',
            'test' => null,
        ], $messageReturnMock->jsonSerialize());
    }

    public function testJsonSerializeWithNullablePropertySet(): void
    {
        $messageReturnMock = new MessageReturnMock('done', 'extra');

        $this->assertSame([
            'data' => 'done',
            'test' => 'extra',
        ], $messageReturnMock->jsonSerialize());
    }

    public function testJsonSerializeIsJsonEncodable(): void
    {
        $messageSubDataMock = new MessageSubDataMock('world');
        $messageDataSecondMock = new MessageDataSecondMock('hello', $messageSubDataMock);

        $json = json_encode($messageDataSecondMock);

        $this->assertSame('{"data":"hello","messageSubDataMock":{"value":"world"}}', $json);
    }

    // propertyNames

    public function testPropertyNamesFlatMessage(): void
    {
        $messageSourceEntity = Source::message(MessageInitMock::class);

        $this->assertSame([
            'MessageInitMock' => ['data'],
        ], $messageSourceEntity->propertyNames);
    }

    public function testPropertyNamesNestedMessage(): void
    {
        $messageSourceEntity = Source::message(MessageDataSecondMock::class);

        $this->assertSame([
            'MessageDataSecondMock' => ['data', 'messageSubDataMock'],
            'MessageSubDataMock' => ['value'],
        ], $messageSourceEntity->propertyNames);
    }

    public function testPropertyNamesKeysAreSorted(): void
    {
        $messageSourceEntity = Source::message(MessageDataSecondMock::class);

        $keys = array_keys($messageSourceEntity->propertyNames);

        $this->assertSame('MessageDataSecondMock', $keys[0]);
        $this->assertSame('MessageSubDataMock', $keys[1]);
    }

    public function testPropertyNamesValuesAreSorted(): void
    {
        $messageSourceEntity = Source::message(MessageReturnMock::class);

        $this->assertSame([
            'MessageReturnMock' => ['data', 'test'],
        ], $messageSourceEntity->propertyNames);
    }

    public function testPropertyNamesKeyIsShortClassName(): void
    {
        $messageSourceEntity = Source::message(MessageDataMock::class);

        $this->assertArrayHasKey('MessageDataMock', $messageSourceEntity->propertyNames);
    }
}
