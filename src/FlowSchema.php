<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use InvalidArgumentException;

class FlowSchema implements \JsonSerializable
{
    /**
     * @param array<mixed> $schema
     */
    public function __construct(
        private array $schema = [],
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

        return $class::schema();
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->schema;
    }
}
