<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTime;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final class Assert
{
    public static function bool(
        mixed $value,
        string $expectedMessage = 'Expected a bool value.',
    ): bool {
        if (!is_bool($value)) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function int(
        mixed $value,
        string $expectedMessage = 'Expected a int value.',
    ): int {
        if (!is_int($value)) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function float(
        mixed $value,
        string $expectedMessage = 'Expected a float value.',
    ): float {
        if (!is_float($value)) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function string(
        mixed $value,
        string $expectedMessage = 'Expected a string value.',
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function nullOrString(
        mixed $value,
        string $expectedMessage = 'Expected a null or string value.',
    ): ?string {
        if (!is_string($value) && $value !== null) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function nullOrInt(
        mixed $value,
        string $expectedMessage = 'Expected a null or int value.',
    ): ?int {
        if (!is_int($value) && $value !== null) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    public static function nullOrFloat(
        mixed $value,
        string $expectedMessage = 'Expected a null or float value.',
    ): ?float {
        if (!is_float($value) && $value !== null) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    /**
     * @return null|array<mixed>
     */
    public static function nullOrArray(
        mixed $value,
        string $expectedMessage = 'Expected a null or array value.',
    ): ?array {
        if (!is_array($value) && $value !== null) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    /**
     * @return array<mixed>
     */
    public static function array(
        mixed $value,
        string $expectedMessage = 'Expected an array value.',
    ): array {
        if (!is_array($value)) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    /**
     * @return string[]
     */
    public static function allString(
        mixed $array,
        string $expectedMessage = 'Expected an array with string values.',
    ): array {
        $array = self::array($array, $expectedMessage);

        foreach ($array as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException($expectedMessage);
            }
        }

        /** @phpstan-ignore-next-line */
        return $array;
    }

    /**
     * @return int[]
     */
    public static function allInt(
        mixed $array,
        string $expectedMessage = 'Expected an array with integer values.',
    ): array {
        $array = self::array($array, $expectedMessage);

        foreach ($array as $value) {
            if (!is_int($value)) {
                throw new InvalidArgumentException($expectedMessage);
            }
        }

        /** @phpstan-ignore-next-line */
        return $array;
    }

    /**
     * @return float[]
     */
    public static function allFloat(
        mixed $array,
        string $expectedMessage = 'Expected an array with float values.',
    ): array {
        $array = self::array($array, $expectedMessage);

        foreach ($array as $value) {
            if (!is_float($value)) {
                throw new InvalidArgumentException($expectedMessage);
            }
        }

        /** @phpstan-ignore-next-line */
        return $array;
    }

    public static function datetime(
        mixed $value,
        string $expectedMessage = 'Expected an datetime value.',
    ): DateTime {
        $value = self::string($value);

        try {
            return new DateTime($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException($expectedMessage, 0, $exception);
        }
    }

    public static function datetimeImmutable(
        mixed $value,
        string $expectedMessage = 'Expected an datetime value.',
    ): DateTimeImmutable {
        $value = self::string($value);

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException($expectedMessage, 0, $exception);
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $object
     * @return class-string<T>
     */
    public static function classString(
        mixed $value,
        string $object,
        string $expectedMessage = 'Expected an ClassString value.',
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException($expectedMessage);
        }

        if ($value !== $object && !is_subclass_of($value, $object)) {
            throw new InvalidArgumentException(sprintf('Expected a subclass string of "%s", got "%s".', $object, $value));
        }

        return $value;
    }

    /**
     * @template T of object
     * @param class-string<T> $object
     * @return T
     */
    public static function object(
        mixed $value,
        string $object,
        string $expectedMessage = 'Expected an Object value.',
    ): object {
        if (!$value instanceof $object) {
            throw new InvalidArgumentException($expectedMessage);
        }

        return $value;
    }

    /**
     * @template T of object
     * @param class-string<T> $object
     */
    public static function isValidClassString(mixed $value, string $object): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return $value === $object || is_subclass_of($value, $object);
    }

    public static function isHash(mixed $value): bool
    {
        $value = self::string($value, 'Expected a string value for hash validation.');

        return Uuid::isValid($value);
    }
}
