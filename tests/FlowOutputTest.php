<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\StubMock;
use Wundii\Flowcrafter\FlowOutput;

final class FlowOutputTest extends TestCase
{
    public function testCreateAndGetters(): void
    {
        $flowOutput = FlowOutput::create(StubMock::class, 'hello world');

        $this->assertSame(StubMock::class, $flowOutput->getStubSource());
        $this->assertSame('hello world', $flowOutput->getContent());
    }

    public function testJsonSerialize(): void
    {
        $flowOutput = FlowOutput::create(StubMock::class, 'some output');

        $this->assertSame([
            'class' => StubMock::class,
            'content' => 'some output',
        ], $flowOutput->jsonSerialize());
    }

    public function testJsonEncode(): void
    {
        $flowOutput = FlowOutput::create(StubMock::class, 'encoded');
        $json = json_encode($flowOutput);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame(StubMock::class, $decoded['class']);
        $this->assertSame('encoded', $decoded['content']);
    }
}
