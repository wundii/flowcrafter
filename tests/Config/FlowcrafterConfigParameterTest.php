<?php

declare(strict_types=1);

namespace Tests\Config;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Config\FlowcrafterConfigParameter;
use Wundii\Flowcrafter\Config\OptionEnum;

final class FlowcrafterConfigParameterTest extends TestCase
{
    private FlowcrafterConfigParameter $flowcrafterConfigParameter;

    protected function setUp(): void
    {
        $this->flowcrafterConfigParameter = new class() extends FlowcrafterConfigParameter {
            public function set(OptionEnum $optionEnum, mixed $value): void
            {
                $this->setParameter($optionEnum, $value);
            }
        };
    }

    public function testHasReturnsTrueWhenSet(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_HOST, 'localhost');
        $this->assertTrue($this->flowcrafterConfigParameter->has(OptionEnum::SERVER_HOST));
    }

    public function testHasReturnsFalseWhenNotSet(): void
    {
        $this->assertFalse($this->flowcrafterConfigParameter->has(OptionEnum::SERVER_HOST));
    }

    public function testGetBooleanReturnsValue(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_HTTPS, true);
        $this->assertTrue($this->flowcrafterConfigParameter->getBoolean(OptionEnum::SERVER_HTTPS));
    }

    public function testGetBooleanThrowsOnWrongType(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_HTTPS, 'yes');
        $this->expectException(InvalidArgumentException::class);
        $this->flowcrafterConfigParameter->getBoolean(OptionEnum::SERVER_HTTPS);
    }

    public function testGetIntegerReturnsValue(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_PORT, 8080);
        $this->assertSame(8080, $this->flowcrafterConfigParameter->getInteger(OptionEnum::SERVER_PORT));
    }

    public function testGetIntegerThrowsOnWrongType(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_PORT, '8080');
        $this->expectException(InvalidArgumentException::class);
        $this->flowcrafterConfigParameter->getInteger(OptionEnum::SERVER_PORT);
    }

    public function testGetStringReturnsValue(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_HOST, 'localhost');
        $this->assertSame('localhost', $this->flowcrafterConfigParameter->getString(OptionEnum::SERVER_HOST));
    }

    public function testGetStringThrowsOnWrongType(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_HOST, 42);
        $this->expectException(InvalidArgumentException::class);
        $this->flowcrafterConfigParameter->getString(OptionEnum::SERVER_HOST);
    }

    public function testGetNullOrStringReturnsValue(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_HOST, 'host');
        $this->assertSame('host', $this->flowcrafterConfigParameter->getNullOrString(OptionEnum::SERVER_HOST));
    }

    public function testGetNullOrStringReturnsNull(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_HOST, null);
        $this->assertNull($this->flowcrafterConfigParameter->getNullOrString(OptionEnum::SERVER_HOST));
    }

    public function testGetNullOrIntReturnsValue(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_PORT, 9000);
        $this->assertSame(9000, $this->flowcrafterConfigParameter->getNullOrInt(OptionEnum::SERVER_PORT));
    }

    public function testGetNullOrIntReturnsNull(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_PORT, null);
        $this->assertNull($this->flowcrafterConfigParameter->getNullOrInt(OptionEnum::SERVER_PORT));
    }

    public function testGetNullOrFloatReturnsValue(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_PORT, 3.14);
        $this->assertEqualsWithDelta(3.14, $this->flowcrafterConfigParameter->getNullOrFloat(OptionEnum::SERVER_PORT), PHP_FLOAT_EPSILON);
    }

    public function testGetNullOrFloatReturnsNull(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::SERVER_PORT, null);
        $this->assertNull($this->flowcrafterConfigParameter->getNullOrFloat(OptionEnum::SERVER_PORT));
    }

    public function testGetArrayWithStringsReturnsValue(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::DEPENDENCIES_INJECTION, ['a', 'b', 'c']);
        $this->assertSame(['a', 'b', 'c'], $this->flowcrafterConfigParameter->getArrayWithStrings(OptionEnum::DEPENDENCIES_INJECTION));
    }

    public function testGetArrayWithStringsThrowsOnNonStringValues(): void
    {
        $this->flowcrafterConfigParameter->set(OptionEnum::DEPENDENCIES_INJECTION, [1, 2]);
        $this->expectException(InvalidArgumentException::class);
        $this->flowcrafterConfigParameter->getArrayWithStrings(OptionEnum::DEPENDENCIES_INJECTION);
    }
}
