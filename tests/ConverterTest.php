<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Tests\MockClass\StubMock;
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
            type: 'flow.workflow.v1',
        );

        $json = Converter::flowToJson($flow);

        $expectedJson = json_encode([
            'source' => WorkflowMock::class,
            'subject' => null,
            'type' => 'flow.workflow.v1',
            'flowSchema' => [
                'type' => 'flow.workflow.v1',
                'stubs' => [
                    StubMock::class,
                ],
            ],
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
            'subject' => '/workflow/1',
            'type' => 'flow.workflow.v1',
            'hash' => $hash,
            'time' => $datetime->format(DateTimeInterface::ATOM),
            'messages' => [],
        ]);

        $flow = Converter::jsonToFlow($json);

        $expectedFlow = new Flow(
            source: WorkflowMock::class,
            type: 'flow.workflow.v1',
            flowSchema: FlowSchema::create(WorkflowMock::class),
            time: $flow->getTime(),
            hash: $hash,
            subject: '/workflow/1',
            messages: [],
        );

        $this->assertSame($expectedFlow->getSource(), $flow->getSource());
        $this->assertSame($expectedFlow->getSubject(), $flow->getSubject());
        $this->assertSame($expectedFlow->getType(), $flow->getType());
        $this->assertEquals($expectedFlow->getFlowSchema(), $flow->getFlowSchema());
        $this->assertEquals($expectedFlow->getTime(), $flow->getTime());
        $this->assertSame($expectedFlow->getHash(), $flow->getHash());
        $this->assertSame($expectedFlow->getMessages(), $flow->getMessages());
    }
}
