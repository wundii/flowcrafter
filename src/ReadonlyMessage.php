<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\MessageInterface;

class ReadonlyMessage implements MessageInterface
{
    /**
     * @param array<string, mixed> $rawData
     */
    public function __construct(
        private readonly string $originalClass,
        private readonly array $rawData,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    public function getOriginalClass(): string
    {
        return $this->originalClass;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
