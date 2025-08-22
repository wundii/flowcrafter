<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use JsonSerializable;
use Wundii\Flowcrafter\Interface\FlowInterface;

readonly class FlowSchema implements JsonSerializable
{
    /**
     * @param Stub[] $stubs
     */
    public function __construct(
        private string $type,
        private array $stubs,
    ) {
    }

    /**
     * @param class-string $class
     */
    public static function create(string $class): self
    {
        Assert::classString(
            $class,
            FlowInterface::class,
            sprintf('Class "%s" does not implement FlowInterface.', $class),
        );

        return $class::schema();
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'stubs' => $this->stubs,
        ];
    }
}
