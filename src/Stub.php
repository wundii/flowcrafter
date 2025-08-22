<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use InvalidArgumentException;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class Stub implements JsonSerializable
{
    private MessageEnum $messageEnum;

    /**
     * @param class-string[] $messages
     */
    public function __construct(
        private readonly string $source,
        private readonly array $messages,
        private readonly string $hash,
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

            $interfaces = class_implements($message);
            $this->messageEnum = match (true) {
                in_array(MessageInitInterface::class, $interfaces, true) => MessageEnum::INIT,
                in_array(MessageDataInterface::class, $interfaces, true) => MessageEnum::DATA,
                in_array(MessageReturnInterface::class, $interfaces, true) => MessageEnum::RETURN,
                default => throw new InvalidArgumentException(sprintf('Message "%s" does not implement any known message interface.', $message)),
            };
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

    public function getMessageEnum(): MessageEnum
    {
        return $this->messageEnum;
    }

    /**
     * @return array<string, string|mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'source' => $this->source,
            'messageEnum' => $this->messageEnum->value,
            'messages' => $this->messages,
            'hash' => $this->hash,
        ];
    }
}
