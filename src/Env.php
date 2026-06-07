<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

final class Env
{
    public static function string(string $key, string $default = ''): string
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
