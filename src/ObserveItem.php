<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\FlowInterface;

final readonly class ObserveItem
{
    /**
     * @param class-string<FlowInterface> $flowSource
     * @param array<mixed> $message
     */
    public function __construct(
        private int $queueId,
        private string $type,
        private string $flowSource,
        private string $flowSHash,
        private string $messageSource,
        private array $message,
    ) {
    }

    public function getQueueId(): int
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

    public function getFlowSHash(): string
    {
        return $this->flowSHash;
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
}
