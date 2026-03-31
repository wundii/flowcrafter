<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Enum;

enum StatusEnum: int
{
    case IN_PROGRESS = 0;
    case IN_PROGRESS_EXCEEDED = 1;
    case OK = 2;
    case WARNING = 3;
    case FAILED = 4;

    public static function fromName(string $name): self
    {
        return constant(self::class . "::{$name}");
    }
}
