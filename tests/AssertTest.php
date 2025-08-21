<?php

declare(strict_types=1);

namespace Tests;

use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Interface\MessageInitInterface;

final class AssertTest extends TestCase
{
    public function testBoolPasses(): void
    {
        $this->assertTrue(Assert::bool(true));
        $this->assertFalse(Assert::bool(false));
    }

    public function testBoolFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::bool(1);
    }

    public function testIntPasses(): void
    {
        $this->assertSame(42, Assert::int(42));
        $this->assertSame(0, Assert::int(0));
    }

    public function testIntFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::int('42');
    }

    public function testFloatPasses(): void
    {
        $this->assertEqualsWithDelta(3.14, Assert::float(3.14), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.0, Assert::float(0.0), PHP_FLOAT_EPSILON);
    }

    public function testFloatFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::float('3.14');
    }

    public function testStringPasses(): void
    {
        $this->assertSame('hello', Assert::string('hello'));
        $this->assertSame('', Assert::string(''));
    }

    public function testStringFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::string(123);
    }

    public function testArrayPasses(): void
    {
        $arr = [
            'a' => 1,
            'b' => 2,
        ];
        $this->assertSame($arr, Assert::array($arr));
    }

    public function testArrayFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::array('not-an-array');
    }

    public function testDatetimePasses(): void
    {
        $this->assertEquals(new DateTime('2025-08-18 18:09:12'), Assert::datetime('2025-08-18 18:09:12'));
    }

    public function testDatetimeFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::datetime('#');
    }

    public function testDatetimeImmutablePasses(): void
    {
        $this->assertEquals(new DateTimeImmutable('2025-08-18 18:09:12'), Assert::datetimeImmutable('2025-08-18 18:09:12'));
    }

    public function testDatetimeImmutableFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::datetimeImmutable('#');
    }

    public function testClassStringPasses(): void
    {
        $this->assertSame(MessageInitInterface::class, Assert::classString(MessageInitInterface::class, MessageInitInterface::class));
    }

    public function testClassStringFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::classString(1337, MessageInitInterface::class);
        Assert::classString('not-an-object', MessageInitInterface::class);
    }

    public function testObjectPasses(): void
    {
        $messageStub = $this->createStub(MessageInitInterface::class);
        $this->assertSame($messageStub, Assert::object($messageStub, MessageInitInterface::class));
    }

    public function testObjectFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::object('not-an-object', MessageInitInterface::class);
    }

    public function testIsHashPasses(): void
    {
        $hash = Uuid::uuid7()->toString();
        $this->assertTrue(Assert::isHash($hash));
    }

    public function testIsHashFails(): void
    {
        $this->assertFalse(Assert::isHash('not-a-valid-hash'));
    }
}
