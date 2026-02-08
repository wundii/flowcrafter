<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageReturnInterface;

readonly class MessageReturnMock implements MessageReturnInterface
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
