<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageDataSecondMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\NextStubMock;
use Tests\MockClass\OtherStubMock;
use Tests\MockClass\PostStubMock;
use Tests\MockClass\StubMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Converter;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowMessage;
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
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );

        $json = Converter::flowToJson($flow);

        $expectedJson = json_encode([
            'flowSource' => WorkflowMock::class,
            'flowSubject' => null,
            'flowType' => 'flow.workflow.v1',
            'flowSchema' => [
                'type' => 'flow.workflow.v1',
                'stubs' => [
                    Stub::create(StubMock::class, [MessageInitMock::class]),
                    Stub::create(NextStubMock::class, [MessageDataMock::class]),
                    Stub::create(OtherStubMock::class, [MessageDataMock::class]),
                    Stub::create(PostStubMock::class, [MessageDataMock::class, MessageDataSecondMock::class]),
                ],
            ],
            'flowSchemaHash' => '70bc15c492ee6a58bf9707792754d776',
            'flowHash' => $flow->getHash(),
            'time' => $flow->getTime()->format(DateTimeInterface::ATOM),
            'flowMessages' => [],
        ]);

        $this->assertSame($expectedJson, $json);
    }

    public function testFlowToJsonWithMessages(): void
    {
        Uuid::appendUuidStock([
            '0198ce36-3a94-7125-9ac7-88902e8ff000', #$json::flowSchema::stubs::stub::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff001', #$json::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff002', #$json::runtimeHash
            '0198ce36-3a94-7125-9ac7-88902e8ff002', #$expectedJson::flowMessage::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff000', #$expectedJson::flowSchema::stubs::stub::hash
        ]);

        $flow = Flow::create(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $flow->addMessage(
            FlowMessage::create(
                flowHash: $flow->getHash(),
                flowRuntimeHash: $flow->getRuntimeHash(),
                stubSource: StubMock::class,
                messageTypeEnum: messageTypeEnum::FINISH,
                predecessorHash: null,
                message: new MessageInitMock('test data'),
                time: $flow->getTime(),
                hash: Uuid::uuid7($flow->getTime())->toString(),
            ),
        );

        $json = Converter::flowToJson($flow);

        $expectedJson = json_encode([
            'flowSource' => WorkflowMock::class,
            'flowSubject' => null,
            'flowType' => 'flow.workflow.v1',
            'flowSchema' => [
                'type' => 'flow.workflow.v1',
                'stubs' => [
                    Stub::create(StubMock::class, [MessageInitMock::class]),
                    Stub::create(NextStubMock::class, [MessageDataMock::class]),
                    Stub::create(OtherStubMock::class, [MessageDataMock::class]),
                    Stub::create(PostStubMock::class, [MessageDataMock::class, MessageDataSecondMock::class]),
                ],
            ],
            'flowSchemaHash' => '70bc15c492ee6a58bf9707792754d776',
            'flowHash' => $flow->getHash(),
            'time' => $flow->getTime()->format(DateTimeInterface::ATOM),
            'flowMessages' => [
                [
                    'flowHash' => $flow->getHash(),
                    'flowRuntimeHash' => $flow->getRuntimeHash(),
                    'stubSource' => StubMock::class,
                    'messageType' => 'finish',
                    'messageSource' => MessageInitMock::class,
                    'message' => [
                        'data' => 'test data',
                    ],
                    'time' => $flow->getTime()->format(DateTimeInterface::ATOM),
                    'hash' => Uuid::uuid7($flow->getTime())->toString(),
                    'predecessorHash' => null,
                ],
            ],
        ]);

        $this->assertSame($expectedJson, $json);
    }

    public function testJsonToFlowWithoutMessages(): void
    {
        Uuid::appendUuidStock([
            '0198ce36-3a94-7125-9ac7-88902e8ff000', #$flow::hash + $expectedFlow::stub::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff001', #$flow::runtimeHash
            '0198ce36-3a94-7125-9ac7-88902e8ff002', #$flow::flowMessage::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff003', #$expectedFlow::flowSchema::stubs::stub::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff001', #$expectedFlow::flowMessage::flowRuntimeHash
            '0198ce36-3a94-7125-9ac7-88902e8ff002', #$expectedFlow::flowMessage::hash
        ]);

        $datetime = new DateTimeImmutable();
        $hash = Uuid::uuid7($datetime)->toString();
        $json = json_encode([
            'flowSource' => WorkflowMock::class,
            'flowSubject' => '/workflow/1',
            'flowType' => 'flow.workflow.v1',
            'flowHash' => $hash,
            'time' => $datetime->format(DateTimeInterface::ATOM),
            'flowMessages' => [],
        ]);

        $flow = Converter::jsonToFlow($json);

        $expectedFlow = new Flow(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSchema: FlowSchema::create(WorkflowMock::class),
            time: $flow->getTime(),
            flowHash: $hash,
            flowSubject: '/workflow/1',
            flowMessages: [],
        );

        $this->assertSame($expectedFlow->getSource(), $flow->getSource());
        $this->assertSame($expectedFlow->getSubject(), $flow->getSubject());
        $this->assertSame($expectedFlow->getType(), $flow->getType());
        $this->assertEquals($expectedFlow->getSchema(), $flow->getSchema());
        $this->assertSame($expectedFlow->getHash(), $flow->getHash());
        $this->assertEquals($expectedFlow->getTime(), $flow->getTime());
        $this->assertSame($expectedFlow->getHash(), $flow->getHash());
        $this->assertEquals($expectedFlow->getFlowMessages(), $flow->getFlowMessages());
    }

    public function testJsonToFlowWithMessages(): void
    {
        Uuid::appendUuidStock([
            '0198ce36-3a94-7125-9ac7-88902e8ff000', #$flow::hash + $expectedFlow::stub::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff001', #$flow::runtimeHash
            '0198ce36-3a94-7125-9ac7-88902e8ff002', #$flow::flowMessage::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff003', #$expectedFlow::flowSchema::stubs::stub::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff001', #$expectedFlow::flowMessage::flowRuntimeHash
            '0198ce36-3a94-7125-9ac7-88902e8ff002', #$expectedFlow::flowMessage::hash
        ]);

        $datetime = new DateTimeImmutable();
        $hash = Uuid::uuid7($datetime)->toString();
        $json = json_encode([
            'flowSource' => WorkflowMock::class,
            'flowSubject' => '/workflow/1',
            'flowType' => 'flow.workflow.v1',
            'flowHash' => $hash,
            'time' => $datetime->format(DateTimeInterface::ATOM),
            'flowMessages' => [
                [
                    'flowHash' => $hash,
                    'flowRuntimeHash' => Uuid::uuid7($datetime)->toString(),
                    'subSource' => StubMock::class,
                    'messageType' => 'finish',
                    'messageSource' => MessageInitMock::class,
                    'message' => [
                        'data' => 'test data',
                    ],
                    'time' => $datetime->format(DateTimeInterface::ATOM),
                    'hash' => Uuid::uuid7($datetime)->toString(),
                    'predecessorHash' => null,
                ],
            ],
        ]);

        $flow = Converter::jsonToFlow($json);

        $expectedFlow = new Flow(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSchema: FlowSchema::create(WorkflowMock::class),
            time: $flow->getTime(),
            flowHash: $hash,
            flowSubject: '/workflow/1',
            flowMessages: [
                FlowMessage::create(
                    flowHash: $hash,
                    flowRuntimeHash: Uuid::uuid7($datetime)->toString(),
                    stubSource: StubMock::class,
                    messageTypeEnum: messageTypeEnum::FINISH,
                    predecessorHash: null,
                    message: new MessageInitMock('test data'),
                    time: $flow->getTime(),
                    hash: Uuid::uuid7($datetime)->toString(),
                ),
            ],
        );

        $this->assertSame($expectedFlow->getSource(), $flow->getSource());
        $this->assertSame($expectedFlow->getSubject(), $flow->getSubject());
        $this->assertSame($expectedFlow->getType(), $flow->getType());
        $this->assertEquals($expectedFlow->getSchema(), $flow->getSchema());
        $this->assertSame($expectedFlow->getHash(), $flow->getHash());
        $this->assertEquals($expectedFlow->getTime(), $flow->getTime());
        $this->assertSame($expectedFlow->getHash(), $flow->getHash());
        $this->assertEquals($expectedFlow->getFlowMessages(), $flow->getFlowMessages());
    }

    public function testFlowToDiagram(): void
    {
        $flow = Flow::create(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: '/workflow/1',
        );

        $file = Converter::flowToDiagram(__DIR__, $flow);

        $this->assertFileExists($file);
        $this->assertStringEqualsFile(__DIR__ . '/Files/flow_to_diagram.mmd', file_get_contents($file));

        unlink($file);
        $this->assertFileDoesNotExist($file);
    }
}
