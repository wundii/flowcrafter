<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageInterface;

/**
 * Implements MessageInterface directly without extending AbstractMessage —
 * used to verify that FlowPreflight rejects messages that can't be processed
 * by the runtime (Source::message() requires AbstractMessage).
 */
final readonly class BareMessageInterfaceMock implements MessageInterface
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
