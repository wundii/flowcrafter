<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\StubMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Converter;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Stub;
use Wundii\Flowcrafter\Uuid;

final class ConverterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Uuid::reset();
    }

    public function testFlowToJsonWithoutMessages(): void
    {
        Uuid::appendUuidStock([
            '0198ce36-3a94-7125-9ac7-88902e8ff000', #$json::flowSchema::stubs::stub::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff001', #$json::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff002', #$json::runtimeHash
            '0198ce36-3a94-7125-9ac7-88902e8ff000', #$expectedJson::flowSchema::stubs::stub::hash
        ]);

        $flow = Flow::create(
            type: 'flow.workflow.v1',
            source: WorkflowMock::class,
        );

        $json = Converter::flowToJson($flow);

        $expectedJson = json_encode([
            'source' => WorkflowMock::class,
            'subject' => null,
            'type' => 'flow.workflow.v1',
            'flowSchema' => [
                'type' => 'flow.workflow.v1',
                'stubs' => [
                    Stub::create(StubMock::class, [MessageInitMock::class]),
                ],
            ],
            'flowHash' => '60d61b3722fb9702c63ed70b802d08d6',
            'hash' => $flow->getHash(),
            'runtimeHash' => $flow->getRuntimeHash(),
            'time' => $flow->getTime()->format(DateTimeInterface::ATOM),
            'messages' => [],
        ]);

        // debugging
        // dd($json, $expectedJson);

        $this->assertSame($expectedJson, $json);
    }

    public function testJsonToFlowWithoutMessages(): void
    {
        Uuid::appendUuidStock([
            '0198ce36-3a94-7125-9ac7-88902e8ff000', #$flow::hash + $expectedFlow::stub::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff001', #$flow::flowSchema::stubs::stub::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff002', #$flow::runtimeHash
            '0198ce36-3a94-7125-9ac7-88902e8ff001', #$expectedFlow::flowSchema::stubs::stub::hash
        ]);

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
            type: 'flow.workflow.v1',
            source: WorkflowMock::class,
            flowSchema: FlowSchema::create(WorkflowMock::class),
            time: $flow->getTime(),
            hash: $hash,
            subject: '/workflow/1',
            messages: [],
        );

        // debugging
        // dd($flow, $expectedFlow);

        $this->assertSame($expectedFlow->getSource(), $flow->getSource());
        $this->assertSame($expectedFlow->getSubject(), $flow->getSubject());
        $this->assertSame($expectedFlow->getType(), $flow->getType());
        $this->assertEquals($expectedFlow->getFlowSchema(), $flow->getFlowSchema());
        $this->assertSame($expectedFlow->getFlowHash(), $flow->getFlowHash());
        $this->assertEquals($expectedFlow->getTime(), $flow->getTime());
        $this->assertSame($expectedFlow->getHash(), $flow->getHash());
        $this->assertSame($expectedFlow->getMessages(), $flow->getMessages());
    }
}
