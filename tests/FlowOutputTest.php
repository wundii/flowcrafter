<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\StepMock;
use Wundii\Flowcrafter\FlowOutput;

final class FlowOutputTest extends TestCase
{
    public function testCreateAndGetters(): void
    {
        $flowOutput = FlowOutput::create(StepMock::class, 'hello world');

        $this->assertSame(StepMock::class, $flowOutput->getStepSource());
        $this->assertSame('hello world', $flowOutput->getContent());
    }

    public function testJsonSerialize(): void
    {
        $flowOutput = FlowOutput::create(StepMock::class, 'some output');

        $this->assertSame([
            'class' => StepMock::class,
            'content' => 'some output',
        ], $flowOutput->jsonSerialize());
    }

    public function testJsonEncode(): void
    {
        $flowOutput = FlowOutput::create(StepMock::class, 'encoded');
        $json = json_encode($flowOutput);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame(StepMock::class, $decoded['class']);
        $this->assertSame('encoded', $decoded['content']);
    }
}
