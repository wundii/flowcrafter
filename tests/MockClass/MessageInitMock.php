<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageInitInterface;

readonly class MessageInitMock implements MessageInitInterface
{
    public function __construct(
        private string $data,
    ) {
    }

    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->data,
        ];
    }
}
