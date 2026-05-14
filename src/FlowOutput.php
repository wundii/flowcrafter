<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use JsonSerializable;
use Wundii\Flowcrafter\Interface\StepInterface;

final readonly class FlowOutput implements JsonSerializable
{
    /**
     * @param class-string<StepInterface> $stepSource
     */
    public function __construct(
        private string $stepSource,
        private string $content,
    ) {
    }

    /**
     * @param class-string<StepInterface> $stepSource
     */
    public static function create(string $stepSource, string $content): self
    {
        return new self(
            stepSource: $stepSource,
            content: $content,
        );
    }

    public function getStepSource(): string
    {
        return $this->stepSource;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return [
            'class' => $this->stepSource,
            'content' => $this->content,
        ];
    }
}
