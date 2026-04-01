<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageDataSecondMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageReturnMock;
use Tests\MockClass\MessageSubDataMock;

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
        $messageInitMock = new MessageInitMock('hello');

        $this->assertSame([
            'MessageInitMock' => ['data'],
        ], $messageInitMock->propertyNames());
    }

    public function testPropertyNamesNestedMessage(): void
    {
        $messageDataSecondMock = new MessageDataSecondMock('hello', new MessageSubDataMock('world'));

        $this->assertSame([
            'MessageDataSecondMock' => ['data', 'messageSubDataMock'],
            'MessageSubDataMock' => ['value'],
        ], $messageDataSecondMock->propertyNames());
    }

    public function testPropertyNamesKeysAreSorted(): void
    {
        $messageDataSecondMock = new MessageDataSecondMock('hello', new MessageSubDataMock('world'));

        $keys = array_keys($messageDataSecondMock->propertyNames());

        $this->assertSame('MessageDataSecondMock', $keys[0]);
        $this->assertSame('MessageSubDataMock', $keys[1]);
    }

    public function testPropertyNamesValuesAreSorted(): void
    {
        $messageReturnMock = new MessageReturnMock('done', 'extra');

        $this->assertSame([
            'MessageReturnMock' => ['data', 'test'],
        ], $messageReturnMock->propertyNames());
    }

    public function testPropertyNamesKeyIsShortClassName(): void
    {
        $messageDataMock = new MessageDataMock('hello');

        $this->assertArrayHasKey('MessageDataMock', $messageDataMock->propertyNames());
    }
}
