<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\MessageInterface;

class FlowMessage implements JsonSerializable
{
    public function __construct(
        private readonly string $flowHash,
        private readonly string $flowRuntimeHash,
        private MessageTypeEnum $messageTypeEnum,
        private readonly string $source,
        private readonly MessageInterface $message,
        private readonly DateTimeImmutable $time,
        private readonly string $hash,
        private readonly ?string $predecessorHash = null,
    ) {
        Assert::classString(
            $source,
            MessageInterface::class,
            sprintf('Message source class "%s" does not implement FlowInterface.', $source)
        );
    }

    public static function create(
        string $flowHash,
        string $flowRuntimeHash,
        MessageTypeEnum $messageTypeEnum,
        ?string $predecessorHash,
        MessageInterface $message,
        ?DateTimeImmutable $time = null,
        ?string $hash = null,
    ): self {
        $time = $time ?? new DateTimeImmutable();

        return new self(
            flowHash: $flowHash,
            flowRuntimeHash: $flowRuntimeHash,
            messageTypeEnum: $messageTypeEnum,
            source: get_class($message),
            message: $message,
            time: $time,
            hash: $hash ?? Uuid::uuid7($time)->toString(),
            predecessorHash: $predecessorHash,
        );
    }

    public function setFinish(): void
    {
        if ($this->messageTypeEnum === MessageTypeEnum::FINISH) {
            throw new InvalidArgumentException('FlowMessage is already marked as FINISH.');
        }

        $this->messageTypeEnum = MessageTypeEnum::FINISH;
    }

    public function getFlowHash(): string
    {
        return $this->flowHash;
    }

    public function getFlowRuntimeHash(): string
    {
        return $this->flowRuntimeHash;
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
            'flowRuntimeHash' => $this->flowRuntimeHash,
            'messageType' => $this->messageTypeEnum->value,
            'source' => $this->source,
            'message' => $this->message,
            'time' => $this->time->format(DateTimeInterface::ATOM),
            'hash' => $this->hash,
            'predecessorHash' => $this->predecessorHash,
        ];
    }
}
