<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final class Assert
{
    public static function bool(mixed $value, string $expectedMessage = 'Expected a bool value.'): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function int(mixed $value, string $expectedMessage = 'Expected a int value.'): int
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function float(mixed $value, string $expectedMessage = 'Expected a float value.'): float
    {
        if (!is_float($value)) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function string(mixed $value, string $expectedMessage = 'Expected a string value.'): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    /**
     * @return array<mixed>
     */
    public static function array(mixed $value, string $expectedMessage = 'Expected an array value.'): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function datetime(mixed $value, string $expectedMessage = 'Expected an datetime value.'): DateTime
    {
        if (!$value instanceof DateTime) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function datetimeImmutable(mixed $value, string $expectedMessage = 'Expected an datetime value.'): DateTimeImmutable
    {
        if (!$value instanceof DateTimeImmutable) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function isHash(mixed $value): bool
    {
        $value = self::string($value, 'Expected a string value for hash validation.');

        return Uuid::isValid($value);
    }
}
