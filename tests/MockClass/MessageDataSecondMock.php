<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractMessage;
use Wundii\Flowcrafter\Interface\MessageDataInterface;

readonly class MessageDataSecondMock extends AbstractMessage implements MessageDataInterface
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
