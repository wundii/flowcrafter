<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Uuid;

final class UuidTest extends TestCase
{
    protected function setUp(): void
    {
        Uuid::reset();
    }

    public function testCreateReturnsSingleton(): void
    {
        $uuid1 = Uuid::create();
        $uuid2 = Uuid::create();
        $this->assertSame($uuid1, $uuid2);
    }

    public function testUuid7ReturnsUuidInstance(): void
    {
        $uuid = Uuid::uuid7();

        $this->assertInstanceOf(Uuid::class, $uuid);
    }

    public function testToStringReturnsUuidString(): void
    {
        $uuid = Uuid::uuid7();
        $uuidString = $uuid->toString();

        $this->assertIsString($uuidString);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $uuidString);
    }

    public function testAppendUuidStockAndToString(): void
    {
        $stock = [
            '123e4567-e89b-12d3-a456-426614174000',
            '123e4567-e89b-12d3-a456-426614174001',
        ];

        Uuid::appendUuidStock($stock);
        $uuid = Uuid::uuid7();

        $this->assertSame($stock[0], $uuid->toString());
        $this->assertSame($stock[1], $uuid->toString());
    }

    public function testResetClearsUuidStock(): void
    {
        Uuid::appendUuidStock(['123e4567-e89b-12d3-a456-426614174000']);
        Uuid::reset();

        $uuid = Uuid::uuid7();
        $uuidString = $uuid->toString();

        $this->assertIsString($uuidString);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $uuidString);
    }

    public function testUuid7WithDateTime(): void
    {
        $datetime = new DateTimeImmutable();
        $uuid = Uuid::uuid7($datetime);

        $this->assertInstanceOf(Uuid::class, $uuid);

        $uuidString = $uuid->toString();
        $this->assertIsString($uuidString);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $uuidString);
    }
}
