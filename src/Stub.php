<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use InvalidArgumentException;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

readonly class Stub implements JsonSerializable
{
    public function __construct(
        private string $flowHash,
        private string $source,
        private string $hash,
        private ?string $predecessorHash = null,
    ) {
        if (!class_exists($source)) {
            throw new InvalidArgumentException(sprintf('Stub source class "%s" does not exist.', $source));
        }
    }

    public static function create(
        string $flowHash,
        string $source,
        ?string $hash = null,
        ?string $predecessorHash = null,
    ): self {
        return new self(
            flowHash: $flowHash,
            source: $source,
            hash: $hash ?? Uuid::uuid7()->toString(),
            predecessorHash: $predecessorHash,
        );
    }

    public function getFlowHash(): string
    {
        return $this->flowHash;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getPredecessorHash(): ?string
    {
        return $this->predecessorHash;
    }

    /**
     * @return array<string, string|mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'flowHash' => $this->flowHash,
            'source' => $this->source,
            'hash' => $this->hash,
            'predecessorHash' => $this->predecessorHash,
        ];
    }
}
