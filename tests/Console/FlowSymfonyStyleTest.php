<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;

final class FlowSymfonyStyleTest extends TestCase
{
    public function testFinishApplicationReturnsFalseOnSuccess(): void
    {
        $bufferedOutput = new BufferedOutput();
        $flowSymfonyStyle = new FlowSymfonyStyle(new StringInput(''), $bufferedOutput);

        $this->assertFalse($flowSymfonyStyle->finishApplication('0.01s'));
        $this->assertStringContainsString('Finished in 0.01s', $bufferedOutput->fetch());
    }

    public function testFinishApplicationReturnsTrueAfterIsFailing(): void
    {
        $bufferedOutput = new BufferedOutput();
        $flowSymfonyStyle = new FlowSymfonyStyle(new StringInput(''), $bufferedOutput);
        $flowSymfonyStyle->isFailing();

        $this->assertTrue($flowSymfonyStyle->finishApplication('0.02s'));
        $this->assertStringContainsString('Finished in 0.02s', $bufferedOutput->fetch());
    }

    public function testStartApplicationWritesVersionLine(): void
    {
        $bufferedOutput = new BufferedOutput();
        $flowSymfonyStyle = new FlowSymfonyStyle(new StringInput(''), $bufferedOutput);

        $flowSymfonyStyle->startApplication('1.2.3');

        $rendered = $bufferedOutput->fetch();
        $this->assertStringContainsString('FlowCrafter 1.2.3', $rendered);
        $this->assertStringContainsString(PHP_VERSION, $rendered);
    }
}
