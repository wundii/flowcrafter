<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use DateTimeInterface;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

class Uuid
{
    private UuidFactoryInterface $uuidFactory;

    private UuidInterface $uuid;

    /**
     * @var string[]
     */
    private static array $uuidStock = [];

    private static ?self $instance = null;

    private function __construct(
    ) {
        $this->uuidFactory = new UuidFactory();
        $this->uuid = $this->uuidFactory->uuid7();
    }

    public static function create(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function uuid7(?DateTimeInterface $datetime = null): self
    {
        $uuid = self::create();

        if (!method_exists($uuid->uuidFactory, 'uuid7')) {
            throw new RuntimeException('UUID factory does not support UUIDv7. Please update the Ramsey UUID library.');
        }

        $uuid->uuid = $uuid->uuidFactory->uuid7($datetime);

        return $uuid;
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
