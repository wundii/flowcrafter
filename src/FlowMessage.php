<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class FlowMessage implements JsonSerializable
{
    /**
     * @param class-string<StubInterface> $stubSource
     * @param class-string<MessageInterface> $messageSource
     */
    public function __construct(
        private readonly string $flowHash,
        private readonly string $flowRuntimeHash,
        private readonly string $stubSource,
        private MessageTypeEnum $messageTypeEnum,
        private readonly string $messageSource,
        private readonly MessageInterface $message,
        private readonly DateTimeImmutable $time,
        private readonly string $hash,
        private readonly ?string $predecessorHash = null,
    ) {
        Assert::classString(
            $stubSource,
            StubInterface::class,
            sprintf('Message source class "%s" does not implement StubInterface.', $stubSource)
        );
        Assert::classString(
            $messageSource,
            MessageInterface::class,
            sprintf('Message source class "%s" does not implement MessageInterface.', $messageSource)
        );
    }

    /**
     * @param class-string<StubInterface> $stubSource
     */
    public static function create(
        string $flowHash,
        string $flowRuntimeHash,
        string $stubSource,
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
            stubSource: $stubSource,
            messageTypeEnum: $messageTypeEnum,
            messageSource: get_class($message),
            message: $message,
            time: $time,
            hash: $hash ?? Uuid::uuid7($time)->toString(),
            predecessorHash: $predecessorHash,
        );
    }

    public function setProcess(): void
    {
        if ($this->messageTypeEnum === MessageTypeEnum::PROCESS) {
            throw new InvalidArgumentException('FlowMessage is already marked as PROCESS.');
        }

        $this->messageTypeEnum = MessageTypeEnum::PROCESS;
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

    public function getStubSource(): string
    {
        return $this->stubSource;
    }

    public function getMessageType(): MessageTypeEnum
    {
        return $this->messageTypeEnum;
    }

    public function getMessageSource(): string
    {
        return $this->messageSource;
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
            'stubSource' => $this->stubSource,
            'messageType' => $this->messageTypeEnum->value,
            'messageSource' => $this->messageSource,
            'message' => $this->message,
            'time' => $this->time->format(DateTimeInterface::ATOM),
            'hash' => $this->hash,
            'predecessorHash' => $this->predecessorHash,
        ];
    }
}
