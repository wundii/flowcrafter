<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\FlowInterface;

final readonly class ObserveItem
{
    /**
     * @param class-string<FlowInterface> $flowSource
     * @param array<mixed> $message
     * @param class-string[] $includeStubs
     */
    public function __construct(
        private string $queueId,
        private string $type,
        private string $flowSource,
        private ?string $flowHash,
        private string $messageSource,
        private array $message,
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
     * @return array<mixed>
     */
    public function getMessage(): array
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
}
