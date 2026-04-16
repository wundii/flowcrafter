<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

use JsonSerializable;

interface MessageInterface extends JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): ?array;
}
