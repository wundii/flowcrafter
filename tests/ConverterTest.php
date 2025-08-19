<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Converter;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowSchema;

final class ConverterTest extends TestCase
{
    public function testFlowToJsonWithoutMessages(): void
    {
        $flow = Flow::create(
            WorkflowMock::class,
            subject: '/workflow/42',
            type: 'flow.workflow.v1',
        );

        $json = Converter::flowToJson($flow);

        $expectedJson = json_encode([
            'source' => WorkflowMock::class,
            'subject' => '/workflow/42',
            'type' => 'flow.workflow.v1',
            'hash' => $flow->getHash(),
            'runtimeHash' => $flow->getRuntimeHash(),
            'time' => $flow->getTime()->format(DateTimeInterface::ATOM),
            'messages' => [],
        ]);

        $this->assertSame($expectedJson, $json);
    }

    public function testJsonToFlowWithoutMessages(): void
    {
        $datetime = new DateTimeImmutable();
        $hash = Uuid::uuid7($datetime)->toString();
        $json = json_encode([
            'source' => WorkflowMock::class,
            'subject' => '/workflow/42',
            'type' => 'flow.workflow.v1',
            'hash' => $hash,
            'time' => $datetime->format(DateTimeInterface::ATOM),
            'messages' => [],
        ]);

        $flow = Converter::jsonToFlow($json);

        $expectedFlow = new Flow(
            source: WorkflowMock::class,
            subject: '/workflow/42',
            type: 'flow.workflow.v1',
            flowSchema: FlowSchema::create(WorkflowMock::class),
            time: $flow->getTime(),
            hash: $hash,
            messages: [],
        );

        $this->assertSame($expectedFlow->getSource(), $flow->getSource());
        $this->assertEquals($expectedFlow->getTime(), $flow->getTime());
        $this->assertSame($expectedFlow->getHash(), $flow->getHash());
        $this->assertSame($expectedFlow->getMessages(), $flow->getMessages());
    }
}
