<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractMessage;
use Wundii\Flowcrafter\Interface\MessageInitInterface;

readonly class MessageWithOptionalMock extends AbstractMessage implements MessageInitInterface
{
    public function __construct(
        private string $command,
        private ?string $nullableRequired,
        private ?string $level = null,
        private int $seconds = 60,
    ) {
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getNullableRequired(): ?string
    {
        return $this->nullableRequired;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }
}
