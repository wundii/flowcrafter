<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Env;

final class EnvTest extends TestCase
{
    private const KEY = 'FLOWCRAFTER_ENV_TEST';

    protected function tearDown(): void
    {
        putenv(self::KEY);

        parent::tearDown();
    }

    public function testStringReturnsValueWhenSet(): void
    {
        putenv(self::KEY . '=hello');

        $this->assertSame('hello', Env::string(self::KEY, 'fallback'));
    }

    public function testStringReturnsDefaultWhenUnset(): void
    {
        $this->assertSame('fallback', Env::string(self::KEY, 'fallback'));
    }

    public function testStringReturnsDefaultWhenEmpty(): void
    {
        putenv(self::KEY . '=');

        $this->assertSame('fallback', Env::string(self::KEY, 'fallback'));
    }

    public function testStringDefaultsToEmptyString(): void
    {
        $this->assertSame('', Env::string(self::KEY));
    }

    public function testIntCastsValue(): void
    {
        putenv(self::KEY . '=3306');

        $this->assertSame(3306, Env::int(self::KEY, 5432));
    }

    public function testIntReturnsDefaultWhenUnset(): void
    {
        $this->assertSame(5432, Env::int(self::KEY, 5432));
    }

    public function testBoolParsesTruthyValues(): void
    {
        foreach (['1', 'true', 'yes', 'on'] as $truthy) {
            putenv(self::KEY . '=' . $truthy);
            $this->assertTrue(Env::bool(self::KEY), sprintf('"%s" should be truthy', $truthy));
        }
    }

    public function testBoolParsesFalsyValues(): void
    {
        putenv(self::KEY . '=0');

        $this->assertFalse(Env::bool(self::KEY, true));
    }

    public function testBoolReturnsDefaultWhenUnset(): void
    {
        $this->assertTrue(Env::bool(self::KEY, true));
    }
}
