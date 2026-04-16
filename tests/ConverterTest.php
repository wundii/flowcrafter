<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\EmptyBoolStubMock;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageDataSecondMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageReturnMock;
use Tests\MockClass\NextStubMock;
use Tests\MockClass\OtherStubMock;
use Tests\MockClass\PostStubMock;
use Tests\MockClass\StubMock;
use Tests\MockClass\WorkflowEmptyMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Converter;
use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\Enum\MessageTypeEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowResult;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Source;
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
                    Stub::create(StubMock::class),
                    Stub::create(NextStubMock::class),
                    Stub::create(OtherStubMock::class),
                    Stub::create(PostStubMock::class),
                ],
            ],
            'flowSchemaHash' => '03ff7ff98280189b9a356e81cb9b362c',
            'flowHash' => $flow->getHash(),
            'flowMessages' => [],
            'flowExceptions' => [],
            'flowResults' => [],
            'flowRuns' => [],
            'flowStatus' => 'IN_PROGRESS',
            'isExecutable' => true,
            'isReadOnly' => false,
            'readOnlyReasons' => [],
            'time' => $flow->getTime()->format(DateTimeInterface::RFC3339_EXTENDED),
        ]);

        $this->assertSame($expectedJson, $json);
    }

    public function testFlowToJsonWithMessages(): void
    {
        Uuid::appendUuidStock([
            '0198ce36-3a94-7125-9ac7-88902e8ff000', #$json::flowSchema::stubs::stub::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff001', #$json::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff002', #$json::runtimeHash
            '0198ce36-3a94-7125-9ac7-88902e8ff003', #$json::flowException::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff004', #$json::flowResult::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff002', #$expectedJson::flowMessage::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff003', #$expectedJson::flowException::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff004', #$expectedJson::flowResult::hash
            '0198ce36-3a94-7125-9ac7-88902e8ff000', #$expectedJson::flowSchema::stubs::stub::hash
        ]);

        $file = __FILE__;
        $line = __LINE__;
        $messageSourceEntity = Source::message(MessageInitMock::class);

        $flow = Flow::create(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
        );
        $flow->addMessage(
            FlowMessage::create(
                flowHash: $flow->getHash(),
                flowRuntimeHash: $flow->getRuntimeHash(),
                stubSource: StubMock::class,
                stubHash: '123',
                messageTypeEnum: messageTypeEnum::FINISH,
                messageHash: $messageSourceEntity->messageHash,
                message: new MessageInitMock('test data'),
                predecessorHash: null,
                time: $flow->getTime(),
                hash: Uuid::uuid7($flow->getTime())->toString(),
            ),
        );
        $flow->addException(
            FlowException::create(
                flowHash: $flow->getHash(),
                flowRuntimeHash: $flow->getRuntimeHash(),
                flowType: $flow->getType(),
                stubSource: StubMock::class,
                stubHash: '123',
                code: 1,
                message: 'Exception message',
                file: $file,
                line: $line,
                traceString: 'Stack trace',
                time: $flow->getTime(),
                hash: Uuid::uuid7($flow->getTime())->toString(),
            ),
        );
        $flow->addResult(FlowResult::create(
            flowHash: $flow->getHash(),
            flowRuntimeHash: $flow->getRuntimeHash(),
            stubSource: StubMock::class,
            stubHash: '123',
            result: true,
            time: $flow->getTime(),
            hash: Uuid::uuid7($flow->getTime())->toString(),
        ));
        $flow->addRun(datetime: $flow->getTime());

        $json = Converter::flowToJson($flow);

        $expectedJson = json_encode([
            'flowSource' => WorkflowMock::class,
            'flowSubject' => null,
            'flowType' => 'flow.workflow.v1',
            'flowSchema' => [
                'type' => 'flow.workflow.v1',
                'stubs' => [
                    Stub::create(StubMock::class),
                    Stub::create(NextStubMock::class),
                    Stub::create(OtherStubMock::class),
                    Stub::create(PostStubMock::class),
                ],
            ],
            'flowSchemaHash' => '03ff7ff98280189b9a356e81cb9b362c',
            'flowHash' => $flow->getHash(),
            'flowMessages' => [
                [
                    'flowHash' => $flow->getHash(),
                    'flowRuntimeHash' => $flow->getRuntimeHash(),
                    'stubSource' => StubMock::class,
                    'stubHash' => '123',
                    'messageType' => 'finish',
                    'messageSource' => MessageInitMock::class,
                    'messageHash' => $messageSourceEntity->messageHash,
                    'message' => [
                        'data' => 'test data',
                    ],
                    'time' => $flow->getTime()->format(DateTimeInterface::RFC3339_EXTENDED),
                    'hash' => Uuid::uuid7($flow->getTime())->toString(),
                    'predecessorHash' => null,
                ],
            ],
            'flowExceptions' => [
                [
                    'flowHash' => $flow->getHash(),
                    'flowRuntimeHash' => $flow->getRuntimeHash(),
                    'flowType' => 'flow.workflow.v1',
                    'stubSource' => StubMock::class,
                    'stubHash' => '123',
                    'code' => 1,
                    'message' => 'Exception message',
                    'file' => $file,
                    'line' => $line,
                    'traceString' => 'Stack trace',
                    'time' => $flow->getTime()->format(DateTimeInterface::RFC3339_EXTENDED),
                    'hash' => Uuid::uuid7($flow->getTime())->toString(),
                ],
            ],
            'flowResults' => [
                [
                    'flowHash' => $flow->getHash(),
                    'flowRuntimeHash' => $flow->getRuntimeHash(),
                    'stubSource' => StubMock::class,
                    'stubHash' => '123',
                    'result' => true,
                    'time' => $flow->getTime()->format(DateTimeInterface::RFC3339_EXTENDED),
                    'hash' => Uuid::uuid7($flow->getTime())->toString(),
                ],
            ],
            'flowRuns' => [
                [
                    'flowHash' => $flow->getHash(),
                    'flowRuntimeHash' => $flow->getRuntimeHash(),
                    'flowType' => 'flow.workflow.v1',
                    'flowStubTimings' => [
                        [
                            'stubSource' => StubMock::class,
                            'started' => $flow->getTime()->format(DateTimeInterface::RFC3339_EXTENDED),
                            'ended' => $flow->getTime()->format(DateTimeInterface::RFC3339_EXTENDED),
                            'duration' => 0,
                        ],
                    ],
                    'time' => $flow->getTime()->format(DateTimeInterface::RFC3339_EXTENDED),
                    'queueId' => null,
                ],
            ],
            'flowStatus' => 'FAILED',
            'isExecutable' => true,
            'isReadOnly' => false,
            'readOnlyReasons' => [],
            'time' => $flow->getTime()->format(DateTimeInterface::RFC3339_EXTENDED),
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
            'flowSchemaHash' => '03ff7ff98280189b9a356e81cb9b362c',
            'flowSchema' => [
                'type' => 'flow.workflow.v1',
                'stubs' => [
                    [
                        'source' => StubMock::class,
                        'messages' => [
                            MessageInitMock::class,
                        ],
                        'returnTypes' => [
                            MessageDataMock::class,
                        ],
                        'messageEnum' => 'init',
                    ],
                    [
                        'source' => NextStubMock::class,
                        'messages' => [
                            MessageDataMock::class,
                        ],
                        'returnTypes' => [],
                        'messageEnum' => 'stub',
                    ],
                    [
                        'source' => OtherStubMock::class,
                        'messages' => [
                            MessageDataMock::class,
                        ],
                        'returnTypes' => [
                            MessageDataSecondMock::class,
                        ],
                        'messageEnum' => 'stub',
                    ],
                    [
                        'source' => PostStubMock::class,
                        'messages' => [
                            MessageDataMock::class,
                            MessageDataSecondMock::class,
                        ],
                        'returnTypes' => [
                            MessageReturnMock::class,
                        ],
                        'messageEnum' => 'stub',
                    ],
                ],
            ],
            'flowHash' => $hash,
            'flowMessages' => [],
            'flowExceptions' => [],
            'flowRuns' => [],
            'time' => $datetime->format(DateTimeInterface::RFC3339_EXTENDED),
        ]);

        $flow = Converter::jsonToFlow($json);

        $expectedFlow = new Flow(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSchema: FlowSchema::create(WorkflowMock::class),
            flowSchemaHash: '03ff7ff98280189b9a356e81cb9b362c',
            time: $flow->getTime(),
            flowHash: $hash,
            flowSubject: '/workflow/1',
            flowMessages: [],
            flowExceptions: [],
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
        $messageSourceEntity = Source::message(MessageInitMock::class);
        $hash = Uuid::uuid7($datetime)->toString();
        $json = json_encode([
            'flowSource' => WorkflowMock::class,
            'flowSubject' => '/workflow/1',
            'flowType' => 'flow.workflow.v1',
            'flowHash' => $hash,
            'flowSchema' => [
                'type' => 'flow.workflow.v1',
                'stubs' => [
                    [
                        'source' => StubMock::class,
                        'messages' => [
                            MessageInitMock::class,
                        ],
                        'returnTypes' => [
                            MessageDataMock::class,
                        ],
                        'messageEnum' => 'init',
                    ],
                    [
                        'source' => NextStubMock::class,
                        'messages' => [
                            MessageDataMock::class,
                        ],
                        'returnTypes' => [],
                        'messageEnum' => 'stub',
                    ],
                    [
                        'source' => OtherStubMock::class,
                        'messages' => [
                            MessageDataMock::class,
                        ],
                        'returnTypes' => [
                            MessageDataSecondMock::class,
                        ],
                        'messageEnum' => 'stub',
                    ],
                    [
                        'source' => PostStubMock::class,
                        'messages' => [
                            MessageDataMock::class,
                            MessageDataSecondMock::class,
                        ],
                        'returnTypes' => [
                            MessageReturnMock::class,
                        ],
                        'messageEnum' => 'stub',
                    ],
                ],
            ],
            'flowSchemaHash' => '03ff7ff98280189b9a356e81cb9b362c',
            'flowMessages' => [
                [
                    'flowHash' => $hash,
                    'flowRuntimeHash' => Uuid::uuid7($datetime)->toString(),
                    'stubSource' => StubMock::class,
                    'stubHash' => '123',
                    'messageType' => 'finish',
                    'messageSource' => MessageInitMock::class,
                    'messageHash' => $messageSourceEntity->messageHash,
                    'message' => [
                        'data' => 'test data',
                    ],
                    'time' => $datetime->format(DateTimeInterface::RFC3339_EXTENDED),
                    'hash' => Uuid::uuid7($datetime)->toString(),
                    'predecessorHash' => null,
                ],
            ],
            'time' => $datetime->format(DateTimeInterface::RFC3339_EXTENDED),
        ]);

        $flow = Converter::jsonToFlow($json);

        $expectedFlow = new Flow(
            flowType: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSchema: FlowSchema::create(WorkflowMock::class),
            flowSchemaHash: '03ff7ff98280189b9a356e81cb9b362c',
            time: $flow->getTime(),
            flowHash: $hash,
            flowSubject: '/workflow/1',
            flowMessages: [
                FlowMessage::create(
                    flowHash: $hash,
                    flowRuntimeHash: Uuid::uuid7($datetime)->toString(),
                    stubSource: StubMock::class,
                    stubHash: '123',
                    messageTypeEnum: messageTypeEnum::FINISH,
                    messageHash: $messageSourceEntity->messageHash,
                    message: new MessageInitMock('test data'),
                    predecessorHash: null,
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

    public function testArrayToFlowWithEmptyInitMessage(): void
    {
        $datetime = new DateTimeImmutable();
        $messageSourceEntity = Source::message(EmptyInitMessage::class);
        $flowSchema = FlowSchema::create(WorkflowEmptyMock::class);

        $flowHash = '0198ce36-3a94-7125-9ac7-88902e8ff000';
        $runtimeHash = '0198ce36-3a94-7125-9ac7-88902e8ff001';
        $messageHash = '0198ce36-3a94-7125-9ac7-88902e8ff002';

        $array = [
            'flowSource' => WorkflowEmptyMock::class,
            'flowSubject' => null,
            'flowType' => 'flow.empty.v1',
            'flowHash' => $flowHash,
            'flowSchema' => [
                'type' => 'flow.empty.v1',
                'stubs' => [
                    [
                        'source' => EmptyBoolStubMock::class,
                        'messages' => [EmptyInitMessage::class],
                        'returnTypes' => [],
                        'messageEnum' => 'init',
                    ],
                ],
            ],
            'flowSchemaHash' => $flowSchema->getHash(),
            'flowMessages' => [
                [
                    'flowHash' => $flowHash,
                    'flowRuntimeHash' => $runtimeHash,
                    'stubSource' => EmptyBoolStubMock::class,
                    'stubHash' => '',
                    'messageType' => 'finish',
                    'messageSource' => EmptyInitMessage::class,
                    'messageHash' => $messageSourceEntity->messageHash,
                    'message' => null,
                    'time' => $datetime->format(DateTimeInterface::RFC3339_EXTENDED),
                    'hash' => $messageHash,
                    'predecessorHash' => null,
                ],
            ],
            'flowExceptions' => [],
            'flowRuns' => [],
            'flowResults' => [],
            'time' => $datetime->format(DateTimeInterface::RFC3339_EXTENDED),
        ];

        $flow = Converter::arrayToFlow($array);

        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertSame(WorkflowEmptyMock::class, $flow->getSource());
        $this->assertSame('flow.empty.v1', $flow->getType());
        $this->assertSame($flowHash, $flow->getHash());
        $this->assertNull($flow->getSubject());
        $this->assertSame($flowSchema->getHash(), $flow->getSchemaHash());
        $this->assertCount(1, $flow->getFlowMessages());

        $flowMessage = $flow->getFlowMessages()[0];
        $this->assertInstanceOf(FlowMessage::class, $flowMessage);
        $this->assertInstanceOf(EmptyInitMessage::class, $flowMessage->getMessage());
        $this->assertSame(EmptyInitMessage::class, $flowMessage->getMessageSource());
        $this->assertSame($messageHash, $flowMessage->getHash());
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
