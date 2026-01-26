<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\MessageInterface;

readonly class Message implements JsonSerializable
{
    private string $runtimeHash;

    public function __construct(
        private string $flowHash,
        private MessageTypeEnum $messageTypeEnum,
        private string $source,
        private MessageInterface $message,
        private DateTimeImmutable $time,
        private string $hash,
        private ?string $predecessorHash = null,
    ) {
        Assert::classString(
            $source,
            MessageInterface::class,
            sprintf('Message source class "%s" does not implement FlowInterface.', $source)
        );

        $this->runtimeHash = Uuid::uuid7($time)->toString();
    }

    public static function create(
        string $flowHash,
        MessageTypeEnum $messageTypeEnum,
        ?string $predecessorHash,
        MessageInterface $message,
        ?DateTimeImmutable $time = null,
        ?string $hash = null,
    ): self {
        $time = $time ?? new DateTimeImmutable();

        return new self(
            flowHash: $flowHash,
            messageTypeEnum: $messageTypeEnum,
            source: get_class($message),
            message: $message,
            time: $time,
            hash: $hash ?? Uuid::uuid7($time)->toString(),
            predecessorHash: $predecessorHash,
        );
    }

    public function getFlowHash(): string
    {
        return $this->flowHash;
    }

    public function getMessageType(): MessageTypeEnum
    {
        return $this->messageTypeEnum;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getMessage(): MessageInterface
    {
        return $this->message;
    }

    public function getTime(): DateTimeImmutable
    {
        return $this->time;
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
            'messageType' => $this->messageTypeEnum->value,
            'source' => $this->source,
            'message' => $this->message,
            'time' => $this->time->format(DateTimeInterface::ATOM),
            'hash' => $this->hash,
            'runtimeHash' => $this->runtimeHash,
            'predecessorHash' => $this->predecessorHash,
        ];
    }
}
