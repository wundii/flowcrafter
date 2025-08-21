<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use JsonSerializable;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

readonly class Stub implements JsonSerializable
{
    /**
     * @param class-string[] $messages
     */
    public function __construct(
        private string $source,
        private array $messages,
        private string $hash,
    ) {
        Assert::classString(
            $source,
            StubInterface::class,
            'Source must be an instance of MessageInterface',
        );

        foreach ($messages as $message) {
            Assert::classString(
                $message,
                MessageInterface::class,
                sprintf(
                    '%s: Message "%s" must be an instance of MessageInterface',
                    $source,
                    $message,
                ),
            );
        }
    }

    /**
     * @param class-string[] $messages
     */
    public static function create(
        string $source,
        array $messages,
        ?string $hash = null,
    ): self {
        return new self(
            source: $source,
            messages: $messages,
            hash: $hash ?? Uuid::uuid7()->toString(),
        );
    }

    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return class-string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @return array<string, string|mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'source' => $this->source,
            'messages' => $this->messages,
            'hash' => $this->hash,
        ];
    }
}
