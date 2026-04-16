<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Console\OutputColorEnum;

final class OutputColorEnumTest extends TestCase
{
    public function testGetBrightEnumMapsColorsToBrightVariants(): void
    {
        $this->assertSame(OutputColorEnum::BRIGHT_RED, OutputColorEnum::RED->getBrightEnum());
        $this->assertSame(OutputColorEnum::BRIGHT_GREEN, OutputColorEnum::GREEN->getBrightEnum());
        $this->assertSame(OutputColorEnum::BRIGHT_YELLOW, OutputColorEnum::YELLOW->getBrightEnum());
        $this->assertSame(OutputColorEnum::BRIGHT_BLUE, OutputColorEnum::BLUE->getBrightEnum());
        $this->assertSame(OutputColorEnum::BRIGHT_MAGENTA, OutputColorEnum::MAGENTA->getBrightEnum());
        $this->assertSame(OutputColorEnum::BRIGHT_CYAN, OutputColorEnum::CYAN->getBrightEnum());
        $this->assertSame(OutputColorEnum::BRIGHT_WHITE, OutputColorEnum::WHITE->getBrightEnum());
    }

    public function testGetBrightEnumReturnsSelfForColorsWithoutBrightVariant(): void
    {
        $this->assertSame(OutputColorEnum::BLACK, OutputColorEnum::BLACK->getBrightEnum());
        $this->assertSame(OutputColorEnum::GRAY, OutputColorEnum::GRAY->getBrightEnum());
        $this->assertSame(OutputColorEnum::DEFAULT, OutputColorEnum::DEFAULT->getBrightEnum());
        $this->assertSame(OutputColorEnum::BRIGHT_RED, OutputColorEnum::BRIGHT_RED->getBrightEnum());
    }

    public function testGetBrightValueReturnsStringValue(): void
    {
        $this->assertSame('bright-red', OutputColorEnum::RED->getBrightValue());
        $this->assertSame('bright-green', OutputColorEnum::GREEN->getBrightValue());
        $this->assertSame('default', OutputColorEnum::DEFAULT->getBrightValue());
        $this->assertSame('gray', OutputColorEnum::GRAY->getBrightValue());
    }
}
