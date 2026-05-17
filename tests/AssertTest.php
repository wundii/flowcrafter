<?php

declare(strict_types=1);

namespace Tests;

use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Uuid;

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

    public function testAllStringPasses(): void
    {
        $arr = [
            'a',
            'b',
        ];
        $this->assertSame($arr, Assert::allString($arr));
    }

    public function testAllStringFails(): void
    {
        $arr = [
            12,
            34,
        ];
        $this->expectException(InvalidArgumentException::class);
        Assert::allString($arr);
    }

    public function testAllIntPasses(): void
    {
        $arr = [
            12,
            34,
        ];
        $this->assertSame($arr, Assert::allInt($arr));
    }

    public function testAllIntFails(): void
    {
        $arr = [
            'a',
            'b',
        ];
        $this->expectException(InvalidArgumentException::class);
        Assert::allInt($arr);
    }

    public function testAllFloatPasses(): void
    {
        $arr = [
            12.34,
            56.78,
        ];
        $this->assertSame($arr, Assert::allFloat($arr));
    }

    public function testAllFloatFails(): void
    {
        $arr = [
            'a',
            'b',
        ];
        $this->expectException(InvalidArgumentException::class);
        Assert::allFloat($arr);
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
        $messageStep = $this->createStub(MessageInitInterface::class);
        $this->assertSame($messageStep, Assert::object($messageStep, MessageInitInterface::class));
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

    public function testNullOrIntPasses(): void
    {
        $this->assertSame(42, Assert::nullOrInt(42));
        $this->assertNull(Assert::nullOrInt(null));
    }

    public function testNullOrIntFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::nullOrInt('42');
    }

    public function testNullOrFloatPasses(): void
    {
        $this->assertEqualsWithDelta(1.5, Assert::nullOrFloat(1.5), PHP_FLOAT_EPSILON);
        $this->assertNull(Assert::nullOrFloat(null));
    }

    public function testNullOrFloatFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::nullOrFloat('1.5');
    }

    public function testNullOrStringPasses(): void
    {
        $this->assertSame('hello', Assert::nullOrString('hello'));
        $this->assertNull(Assert::nullOrString(null));
    }

    public function testNullOrStringFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Assert::nullOrString(123);
    }

    public function testIsValidClassStringTrue(): void
    {
        $this->assertTrue(Assert::isValidClassString(MessageInitInterface::class, MessageInitInterface::class));
    }

    public function testIsValidClassStringFalse(): void
    {
        $this->assertFalse(Assert::isValidClassString('not-a-class', MessageInitInterface::class));
        $this->assertFalse(Assert::isValidClassString(42, MessageInitInterface::class));
    }
}
