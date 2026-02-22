<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageReturnInterface;

readonly class MessageReturnMock implements MessageReturnInterface
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

    /**
     * @return array<string, string|null>
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->data,
            'test' => $this->test,
        ];
    }
}
