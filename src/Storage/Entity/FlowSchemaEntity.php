<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage\Entity;

use JsonSerializable;

class FlowSchemaEntity implements JsonSerializable
{
    /**
     * @param array<mixed> $stubs
     */
    public function __construct(
        public string $schemaHash,
        public string $type,
        public array $stubs,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'schemaHash' => $this->schemaHash,
            'type' => $this->type,
            'stubs' => $this->stubs,
        ];
    }
}
