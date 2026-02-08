<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageDataInterface;

readonly class MessageDataMock implements MessageDataInterface
{
    public function __construct(
        private string $data,
    ) {
    }

    public function getData(): string
    {
        return $this->data;
    }
}
