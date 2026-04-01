<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractMessageData;

readonly class MessageDataSecondMock extends AbstractMessageData
{
    public function __construct(
        private string $data,
        private MessageSubDataMock $messageSubDataMock,
    ) {
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getMessageSubDataMock(): MessageSubDataMock
    {
        return $this->messageSubDataMock;
    }
}
