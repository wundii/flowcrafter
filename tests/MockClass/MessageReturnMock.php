<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractMessage;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

readonly class MessageReturnMock extends AbstractMessage implements MessageReturnInterface
{
    public function __construct(
        private string $data,
        private ?string $test = null,
    ) {
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getTest(): ?string
    {
        return $this->test;
    }
}
