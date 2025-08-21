<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeInterface;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

class Uuid
{
    private UuidInterface $uuid;

    /**
     * @var string[]
     */
    private static array $uuidStock = [];

    private function __construct(
        ?DateTimeInterface $datetime = null,
    ) {
        $this->uuid = RamseyUuid::uuid7($datetime);
    }

    public static function uuid7(?DateTimeInterface $datetime = null): self
    {
        return new self($datetime);
    }

    public function toString(): string
    {
        if (self::$uuidStock !== []) {
            return array_shift(self::$uuidStock);
        }

        return $this->uuid->toString();
    }

    public static function reset(): void
    {
        self::$uuidStock = [];
    }

    /**
     * @param string[] $uuids
     */
    public static function appendUuidStock(array $uuids): void
    {
        self::$uuidStock = array_merge(self::$uuidStock, $uuids);
    }
}
