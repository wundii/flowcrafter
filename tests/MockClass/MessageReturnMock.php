<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractMessageReturn;

readonly class MessageReturnMock extends AbstractMessageReturn
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
