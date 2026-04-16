<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use JsonSerializable;
use Wundii\Flowcrafter\Interface\FlowInterface;

final readonly class ObserveItem implements JsonSerializable
{
    /**
     * @param class-string<FlowInterface> $flowSource
     * @param array<mixed> $message
     * @param class-string[] $includeStubs
     */
    public function __construct(
        private string $queueId,
        private string $type,
        private ?string $flowSubject,
        private string $flowSource,
        private ?string $flowHash,
        private string $messageSource,
        private ?array $message,
        private array $includeStubs = [],
    ) {
    }

    public function getQueueId(): string
    {
        return $this->queueId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return class-string<FlowInterface>
     */
    public function getFlowSource(): string
    {
        return $this->flowSource;
    }

    public function getFlowHash(): ?string
    {
        return $this->flowHash;
    }

    public function getMessageSource(): string
    {
        return $this->messageSource;
    }

    /**
     * @return null|array<mixed>
     */
    public function getMessage(): ?array
    {
        return $this->message;
    }

    /**
     * @return class-string[]
     */
    public function getIncludeStubs(): array
    {
        return $this->includeStubs;
    }

    public function getFlowSubject(): ?string
    {
        return $this->flowSubject;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'queueId' => $this->queueId,
            'type' => $this->type,
            'flowSubject' => $this->flowSubject,
            'flowSource' => $this->flowSource,
            'flowHash' => $this->flowHash,
            'messageSource' => $this->messageSource,
            'message' => $this->message,
            'includeStubs' => $this->includeStubs,
        ];
    }
}
