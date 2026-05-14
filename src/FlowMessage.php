<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class FlowMessage implements JsonSerializable
{
    /**
     * @param class-string<StepInterface> $stepSource
     * @param class-string<MessageInterface> $messageSource
     */
    public function __construct(
        private readonly string $flowHash,
        private readonly string $flowRuntimeHash,
        private readonly string $stepSource,
        private readonly string $stepHash,
        private MessageTypeEnum $messageTypeEnum,
        private readonly string $messageSource,
        private readonly string $messageHash,
        private readonly MessageInterface $message,
        private readonly DateTimeImmutable $time,
        private readonly string $hash,
        private readonly ?string $predecessorHash = null,
        bool $skipClassValidation = false,
    ) {
        if (!$skipClassValidation) {
            Assert::classString(
                $stepSource,
                StepInterface::class,
                sprintf('Message source class "%s" does not implement StepInterface.', $stepSource)
            );
            Assert::classString(
                $messageSource,
                MessageInterface::class,
                sprintf('Message source class "%s" does not implement MessageInterface.', $messageSource)
            );
        }
    }

    /**
     * @param class-string<StepInterface> $stepSource
     */
    public static function create(
        string $flowHash,
        string $flowRuntimeHash,
        string $stepSource,
        string $stepHash,
        MessageTypeEnum $messageTypeEnum,
        string $messageHash,
        MessageInterface $message,
        ?string $predecessorHash,
        ?DateTimeImmutable $time = null,
        ?string $hash = null,
    ): self {
        return new self(
            flowHash: $flowHash,
            flowRuntimeHash: $flowRuntimeHash,
            stepSource: $stepSource,
            stepHash: $stepHash,
            messageTypeEnum: $messageTypeEnum,
            messageSource: get_class($message),
            messageHash: $messageHash,
            message: $message,
            time: $time ?? new DateTimeImmutable(),
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

    public function getStepSource(): string
    {
        return $this->stepSource;
    }

    public function getStepHash(): ?string
    {
        return $this->stepHash;
    }

    public function getMessageType(): MessageTypeEnum
    {
        return $this->messageTypeEnum;
    }

    public function getMessageSource(): string
    {
        return $this->messageSource;
    }

    public function getMessageHash(): string
    {
        return $this->messageHash;
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
            'stepSource' => $this->stepSource,
            'stepHash' => $this->stepHash,
            'messageType' => $this->messageTypeEnum->value,
            'messageSource' => $this->messageSource,
            'messageHash' => $this->messageHash,
            'message' => $this->message,
            'time' => $this->time->format(DateTimeInterface::RFC3339_EXTENDED),
            'hash' => $this->hash,
            'predecessorHash' => $this->predecessorHash,
        ];
    }
}
