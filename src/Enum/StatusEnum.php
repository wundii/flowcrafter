<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Enum;

use UnexpectedValueException;

enum StatusEnum: int
{
    case IN_PROGRESS = 0;
    case IN_PROGRESS_EXCEEDED = 1;
    case OK = 2;
    case WARNING = 3;
    case FAILED = 4;

    public static function fromName(string $name): self
    {
        $enum = constant(self::class . ('::' . $name));
        if (!$enum instanceof self) {
            throw new UnexpectedValueException('Unknown status: ' . $name);
        }

        return $enum;
    }
}
