<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use JsonSerializable;
use Wundii\Flowcrafter\Interface\StubInterface;

final readonly class FlowOutput implements JsonSerializable
{
    /**
     * @param class-string<StubInterface> $stubSource
     */
    public function __construct(
        private string $stubSource,
        private string $content,
    ) {
    }

    /**
     * @param class-string<StubInterface> $stubSource
     */
    public static function create(string $stubSource, string $content): self
    {
        return new self(
            stubSource: $stubSource,
            content: $content,
        );
    }

    public function getStubSource(): string
    {
        return $this->stubSource;
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
            'class' => $this->stubSource,
            'content' => $this->content,
        ];
    }
}
