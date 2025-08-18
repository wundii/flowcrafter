<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Ramsey\Uuid\Uuid;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\MessageInterface;

readonly class Message implements JsonSerializable
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private MessageTypeEnum $messageTypeEnum,
        private string $source,
        private array $data,
        private DateTimeImmutable $time,
        private string $hash,
        private string $flowHash,
        private ?string $predecessorHash = null,
    ) {
        if (!class_exists($source)) {
            throw new InvalidArgumentException(sprintf('MessageSource class "%s" does not exist.', $source));
        }

        if (!is_subclass_of($source, MessageInterface::class)) {
            throw new InvalidArgumentException(sprintf('MessageSource class "%s" does not implement FlowInterface.', $source));
        }
    }

    /**
     * @param array<mixed> $data
     */
    public static function create(
        MessageTypeEnum $messageTypeEnum,
        string $flowHash,
        ?string $predecessorHash,
        string $source,
        array $data,
        ?DateTimeImmutable $time = null,
        ?string $hash = null,
    ): self {
        if (!$time instanceof \DateTimeImmutable) {
            $time = new DateTimeImmutable();
        }

        if ($hash === null) {
            $hash = Uuid::uuid7()->toString();
        }

        return new self(
            messageTypeEnum: $messageTypeEnum,
            source: $source,
            data: $data,
            time: $time,
            hash: $hash,
            flowHash: $flowHash,
            predecessorHash: $predecessorHash,
        );
    }

    public function getMessageType(): MessageTypeEnum
    {
        return $this->messageTypeEnum;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getTime(): DateTimeImmutable
    {
        return $this->time;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getFlowHash(): string
    {
        return $this->flowHash;
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
            'messageType' => $this->messageTypeEnum->value,
            'source' => $this->source,
            'data' => $this->data,
            'time' => $this->time->format(DateTimeInterface::ATOM),
            'hash' => $this->hash,
            'flowHash' => $this->flowHash,
            'predecessorHash' => $this->predecessorHash,
        ];
    }
}
