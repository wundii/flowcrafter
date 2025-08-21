<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use InvalidArgumentException;
use JsonSerializable;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

readonly class FlowSchema implements JsonSerializable
{
    /**
     * @param StubInterface[] $stubs
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
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        if (!is_subclass_of($class, FlowInterface::class)) {
            throw new InvalidArgumentException(sprintf('Class "%s" does not implement FlowInterface.', $class));
        }

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
