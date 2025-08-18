<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\MessageInterface;

class Flow implements JsonSerializable
{
    /**
     * @param Message[] $messages
     */
    public function __construct(
        private readonly string $source,
        private readonly string $hash,
        private readonly DateTimeImmutable $time,
        private array $messages = [],
    ) {
        if (!class_exists($source)) {
            throw new InvalidArgumentException(sprintf('FlowSource class "%s" does not exist.', $source));
        }

        if (!is_subclass_of($source, MessageInterface::class)) {
            throw new InvalidArgumentException(sprintf('FlowSource class "%s" does not implement FlowInterface.', $source));
        }

        if ($hash === '') {
            throw new InvalidArgumentException('Hash cannot be empty.');
        }
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getTime(): DateTimeImmutable
    {
        return $this->time;
    }

    public function getLatestMessageTime(): ?DateTimeImmutable
    {
        $messageDates = array_map(
            static fn (Message $message): DateTimeImmutable => $message->getTime(),
            $this->messages,
        );

        if ($messageDates === []) {
            return null;
        }

        return max($messageDates);
    }

    public function getFinishTime(): ?DateTimeImmutable
    {
        $messages = array_filter(
            $this->messages,
            static fn (Message $message): bool => $message->getMessageType() === MessageTypeEnum::FINISH,
        );

        if ($messages === []) {
            return null;
        }

        return current($messages)->getTime();
    }

    /**
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function addMessage(Message $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * @return array<string, string|array<Message>>
     */
    public function jsonSerialize(): array
    {
        return [
            'source' => $this->source,
            'hash' => $this->hash,
            'time' => $this->time->format(DateTimeInterface::ATOM),
            'messages' => $this->messages,
        ];
    }
}
