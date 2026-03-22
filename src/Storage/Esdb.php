<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use RuntimeException;
use Thenativeweb\Eventsourcingdb\Bound;
use Thenativeweb\Eventsourcingdb\BoundType;
use Thenativeweb\Eventsourcingdb\Client;
use Thenativeweb\Eventsourcingdb\EventCandidate;
use Thenativeweb\Eventsourcingdb\IsEventQlQueryTrue;
use Thenativeweb\Eventsourcingdb\IsSubjectPopulated;
use Thenativeweb\Eventsourcingdb\IsSubjectPristine;
use Thenativeweb\Eventsourcingdb\ObserveEventsOptions;
use Thenativeweb\Eventsourcingdb\Order;
use Thenativeweb\Eventsourcingdb\ReadEventsOptions;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Converter;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowResult;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;
use Wundii\Flowcrafter\Stub;

class Esdb implements StorageInterface
{
    public const SOURCE = 'https://flowcrafter';

    public const QUEUE_SUBJECT = '/flow/queue';

    public const TYPE_INSTANCE = 'flowcrafter.flow.instance.v1';

    public const TYPE_MESSAGE = 'flowcrafter.flow.message.v1';

    public const TYPE_EXCEPTION = 'flowcrafter.flow.exception.v1';

    public const TYPE_RESULT = 'flowcrafter.flow.result.v1';

    public const TYPE_QUEUE = 'flowcrafter.flow.queue.v1';

    public const TYPE_RUN = 'flowcrafter.flow.run.v1';

    public const TYPE_SCHEMA = 'flowcrafter.flow.schema.v1';

    public const TYPE_SOURCE_STUB = 'flowcrafter.flow.source.stub.v1';

    protected Client $client;

    public function __construct(
        string $url,
        string $apiToken,
    ) {
        $this->client = new Client(
            $url,
            $apiToken,
        );
    }

    public function initializeDatabase(): void
    {
        $eventTypesQl = 'FROM e IN eventtypes PROJECT INTO e';
        $eventTypes = $this->client->runEventQlQuery($eventTypesQl);
        $eventTypes = iterator_to_array($eventTypes);

        if (!in_array(self::TYPE_INSTANCE, $eventTypes, true)) {
            $eventType = self::TYPE_INSTANCE;
            $registerEventSchema = [
                'type' => 'object',
                'properties' => [
                    'flowSource' => [
                        'type' => 'string',
                    ],
                    'flowSubject' => [
                        'type' => ['null', 'string'],
                    ],
                    'flowType' => [
                        'type' => 'string',
                    ],
                    'flowSchemaHash' => [
                        'type' => 'string',
                    ],
                    'flowHash' => [
                        'type' => 'string',
                    ],
                    'time' => [
                        'type' => 'string',
                    ],
                ],
                'required' => [
                    'flowSource',
                    'flowSubject',
                    'flowType',
                    'flowSchemaHash',
                    'flowHash',
                    'time',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventType, $registerEventSchema);
        }

        if (!in_array(self::TYPE_RUN, $eventTypes, true)) {
            $eventType = self::TYPE_RUN;
            $registerEventSchema = [
                'type' => 'object',
                'properties' => [
                    'flowHash' => [
                        'type' => 'string',
                    ],
                    'flowRuntimeHash' => [
                        'type' => 'string',
                    ],
                    'time' => [
                        'type' => 'string',
                    ],
                    'queueId' => [
                        'type' => ['null', 'string'],
                    ],
                ],
                'required' => [
                    'flowHash',
                    'flowRuntimeHash',
                    'time',
                    'queueId',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventType, $registerEventSchema);
        }

        if (!in_array(self::TYPE_SCHEMA, $eventTypes, true)) {
            $eventType = self::TYPE_SCHEMA;
            $registerEventSchema = [
                'type' => 'object',
                'properties' => [
                    'type' => [
                        'type' => 'string',
                    ],
                    'stubs' => [
                        'type' => 'array',
                        'properties' => [
                            'source' => [
                                'type' => 'string',
                            ],
                            'messageEnum' => [
                                'type' => 'string',
                            ],
                            'messages' => [
                                'type' => 'array',
                            ],
                            'returnTypes' => [
                                'type' => 'array',
                            ],
                        ],
                        'required' => [
                            'source',
                            'messageEnum',
                            'messages',
                            'returnTypes',
                        ],
                    ],
                ],
                'required' => [
                    'type',
                    'stubs',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventType, $registerEventSchema);
        }

        if (!in_array(self::TYPE_SOURCE_STUB, $eventTypes, true)) {
            $eventType = self::TYPE_SOURCE_STUB;
            $registerEventSchema = [
                'type' => 'object',
                'properties' => [
                    'stubHash' => [
                        'type' => 'string',
                    ],
                    'stubSource' => [
                        'type' => 'string',
                    ],
                    'sourceContent' => [
                        'type' => 'string',
                    ],
                    'time' => [
                        'type' => 'string',
                    ],
                ],
                'required' => [
                    'stubHash',
                    'stubSource',
                    'sourceContent',
                    'time',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventType, $registerEventSchema);
        }

        if (!in_array(self::TYPE_MESSAGE, $eventTypes, true)) {
            $eventType = self::TYPE_MESSAGE;
            $registerEventSchema = [
                'type' => 'object',
                'properties' => [
                    'flowHash' => [
                        'type' => 'string',
                    ],
                    'flowRuntimeHash' => [
                        'type' => 'string',
                    ],
                    'stubSource' => [
                        'type' => 'string',
                    ],
                    'stubHash' => [
                        'type' => 'string',
                    ],
                    'messageType' => [
                        'type' => 'string',
                    ],
                    'messageSource' => [
                        'type' => 'string',
                    ],
                    'message' => [
                        'type' => 'object',
                    ],
                    'time' => [
                        'type' => 'string',
                    ],
                    'hash' => [
                        'type' => 'string',
                    ],
                    'predecessorHash' => [
                        'type' => ['null', 'string'],
                    ],
                ],
                'required' => [
                    'flowHash',
                    'flowRuntimeHash',
                    'stubSource',
                    'stubHash',
                    'messageType',
                    'messageSource',
                    'message',
                    'time',
                    'hash',
                    'predecessorHash',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventType, $registerEventSchema);
        }

        if (!in_array(self::TYPE_EXCEPTION, $eventTypes, true)) {
            $eventType = self::TYPE_EXCEPTION;
            $registerEventSchema = [
                'type' => 'object',
                'properties' => [
                    'flowHash' => [
                        'type' => 'string',
                    ],
                    'flowRuntimeHash' => [
                        'type' => 'string',
                    ],
                    'flowType' => [
                        'type' => 'string',
                    ],
                    'stubSource' => [
                        'type' => 'string',
                    ],
                    'stubHash' => [
                        'type' => 'string',
                    ],
                    'code' => [
                        'type' => 'integer',
                    ],
                    'message' => [
                        'type' => 'string',
                    ],
                    'file' => [
                        'type' => 'string',
                    ],
                    'line' => [
                        'type' => 'integer',
                    ],
                    'traceString' => [
                        'type' => 'string',
                    ],
                    'time' => [
                        'type' => 'string',
                    ],
                    'hash' => [
                        'type' => 'string',
                    ],
                ],
                'required' => [
                    'flowHash',
                    'flowRuntimeHash',
                    'flowType',
                    'stubSource',
                    'stubHash',
                    'code',
                    'message',
                    'file',
                    'line',
                    'traceString',
                    'time',
                    'hash',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventType, $registerEventSchema);
        }

        if (!in_array(self::TYPE_RESULT, $eventTypes, true)) {
            $eventType = self::TYPE_RESULT;
            $registerEventSchema = [
                'type' => 'object',
                'properties' => [
                    'flowHash' => [
                        'type' => 'string',
                    ],
                    'flowRuntimeHash' => [
                        'type' => 'string',
                    ],
                    'stubSource' => [
                        'type' => 'string',
                    ],
                    'stubHash' => [
                        'type' => ['null', 'string'],
                    ],
                    'result' => [
                        'type' => 'boolean',
                    ],
                    'time' => [
                        'type' => 'string',
                    ],
                    'hash' => [
                        'type' => 'string',
                    ],
                ],
                'required' => [
                    'flowHash',
                    'flowRuntimeHash',
                    'stubSource',
                    'stubHash',
                    'result',
                    'time',
                    'hash',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventType, $registerEventSchema);
        }

        if (!in_array(self::TYPE_QUEUE, $eventTypes, true)) {
            $eventType = self::TYPE_QUEUE;
            $registerEventSchema = [
                'type' => 'object',
                'properties' => [
                    'type' => [
                        'type' => 'string',
                    ],
                    'flowSource' => [
                        'type' => 'string',
                    ],
                    'flowHash' => [
                        'type' => ['null', 'string'],
                    ],
                    'messageSource' => [
                        'type' => 'string',
                    ],
                    'message' => [
                        'type' => ['array', 'object'],
                    ],
                ],
                'required' => [
                    'type',
                    'flowSource',
                    'flowHash',
                    'messageSource',
                    'message',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventType, $registerEventSchema);
        }
    }

    public function registerFlowSchema(FlowSchema $flowSchema): void
    {
        $subject = '/flow/schema/' . $flowSchema->getHash();

        $readEventsOptions = new ReadEventsOptions(false);
        if (iterator_to_array($this->client->readEvents($subject, $readEventsOptions)) !== []) {
            return;
        }

        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: self::TYPE_SCHEMA,
            data: $flowSchema->jsonSerialize(),
        );

        $this->client->writeEvents(
            [
                $eventCandidate,
            ],
            [
                new IsSubjectPristine($subject),
                new IsEventQlQueryTrue(
                    'FROM e IN events ' .
                    'WHERE e.data.type == "' . $flowSchema->type() . '" ' .
                    'AND e.type == "' . self::TYPE_SCHEMA . '" ' .
                    'PROJECT INTO COUNT() == 0'
                ),
            ]
        );
    }

    /**
     * @param class-string $stubSource
     */
    public function registerStubSource(string $stubSource): string
    {
        $stubSource = Stub::source($stubSource);

        $subject = '/flow/source/stub/' . $stubSource->stubHash;

        $readEventsOptions = new ReadEventsOptions(false);
        if (iterator_to_array($this->client->readEvents($subject, $readEventsOptions)) !== []) {
            return $stubSource->stubHash;
        }

        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: self::TYPE_SOURCE_STUB,
            data: $stubSource->jsonSerialize(),
        );

        $this->client->writeEvents(
            [
                $eventCandidate,
            ],
            [
                new IsSubjectPristine($subject),
            ]
        );

        return $stubSource->stubHash;
    }

    public function registerFlowInstance(Flow $flow): void
    {
        $subject = '/flow/' . $flow->getHash();

        $readEventsOptions = new ReadEventsOptions(false);
        if (iterator_to_array($this->client->readEvents($subject, $readEventsOptions)) !== []) {
            return;
        }

        $data = $flow->jsonSerialize();
        unset($data['flowSchema']);
        unset($data['flowMessages']);
        unset($data['flowExceptions']);
        unset($data['flowResults']);
        unset($data['flowRuns']);
        unset($data['isExecutable']);

        $subjectSchema = '/flow/schema/' . $flow->getSchema()->getHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: self::TYPE_INSTANCE,
            data: $data,
        );

        $this->client->writeEvents(
            [
                $eventCandidate,
            ],
            [
                new IsSubjectPristine($subject),
                new IsSubjectPopulated($subjectSchema),
            ]
        );
    }

    public function appendFlowRun(Flow $flow, ?string $queueId = null): void
    {
        $subject = '/flow/' . $flow->getHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: self::TYPE_RUN,
            data: [
                'flowHash' => $flow->getHash(),
                'flowRuntimeHash' => $flow->getRuntimeHash(),
                'time' => $flow->getTime()->format(DateTimeInterface::RFC3339_EXTENDED),
                'queueId' => $queueId,
            ],
        );

        $this->client->writeEvents(
            [
                $eventCandidate,
            ],
            [
                new IsSubjectPopulated($subject),
                new IsEventQlQueryTrue(
                    'FROM e IN events ' .
                    'WHERE e.subject == "' . $subject . '" ' .
                    'AND e.type == "' . self::TYPE_INSTANCE . '" ' .
                    'PROJECT INTO COUNT() == 1'
                ),
            ]
        );
    }

    public function appendFlowMessage(FlowMessage $flowMessage): void
    {
        $subject = '/flow/' . $flowMessage->getFlowHash() . '/message/' . $flowMessage->getHash();
        $subjectFlow = '/flow/' . $flowMessage->getFlowHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: self::TYPE_MESSAGE,
            data: $flowMessage->jsonSerialize(),
        );

        $this->client->writeEvents(
            [
                $eventCandidate,
            ],
            [
                new IsSubjectPristine($subject),
                new IsSubjectPopulated($subjectFlow),
            ]
        );
    }

    public function appendFlowException(FlowException $flowException): void
    {
        $subject = '/flow/' . $flowException->getFlowHash() . '/exception/' . $flowException->getHash();
        $subjectFlow = '/flow/' . $flowException->getFlowHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: self::TYPE_EXCEPTION,
            data: $flowException->jsonSerialize(),
        );

        $this->client->writeEvents(
            [
                $eventCandidate,
            ],
            [
                new IsSubjectPopulated($subjectFlow),
            ],
        );
    }

    public function appendFlowResult(FlowResult $flowResult): void
    {
        $subject = '/flow/' . $flowResult->getFlowHash() . '/result/' . $flowResult->getHash();
        $subjectFlow = '/flow/' . $flowResult->getFlowHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: self::TYPE_RESULT,
            data: $flowResult->jsonSerialize(),
        );

        $this->client->writeEvents(
            [
                $eventCandidate,
            ],
            [
                new IsSubjectPopulated($subjectFlow),
            ],
        );
    }

    public function openQueues(): int
    {
        $lastFlowRunWithQueueId = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_RUN . '" ' .
            'AND e.data.queueId != null ' .
            'ORDER BY e.id DESC ' .
            'TOP 1 ' .
            'PROJECT INTO e.data.queueId'
        );
        $lastFlowRunEvent = iterator_to_array($lastFlowRunWithQueueId);
        $lastQueueId = $lastFlowRunEvent[0] ?? '0';

        $events = $this->client->readEvents(
            self::QUEUE_SUBJECT,
            new ReadEventsOptions(
                lowerBound: new Bound(
                    id: $lastQueueId,
                    type: $lastQueueId === '0' ? BoundType::INCLUSIVE : BoundType::EXCLUSIVE,
                ),
            ),
        );

        return count(iterator_to_array($events));
    }

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     */
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, array $message, array $includeStubs = []): void
    {
        Assert::classString($flowSource, FlowInterface::class);
        Assert::classString($messageSource, MessageInterface::class);

        $this->client->writeEvents([
            new EventCandidate(
                source: self::SOURCE,
                subject: self::QUEUE_SUBJECT,
                type: self::TYPE_QUEUE,
                data: [
                    'type' => $type,
                    'flowSource' => $flowSource,
                    'flowHash' => $flowHash,
                    'messageSource' => $messageSource,
                    'message' => $message,
                    'includeStubs' => $includeStubs,
                ],
            ),
        ]);
    }

    /**
     * @return iterable<ObserveItem>
     */
    public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable
    {
        $this->client->abortIn($maxExecutionTimeInSeconds);

        $lastFlowRunWithQueueId = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_RUN . '" ' .
            'AND e.data.queueId != null ' .
            'ORDER BY e.id DESC ' .
            'PROJECT INTO e.data.queueId'
        );
        $lastFlowRunEvent = iterator_to_array($lastFlowRunWithQueueId);
        $lastQueueId = $lastFlowRunEvent[0] ?? '0';

        if (!is_string($lastQueueId)) {
            throw new RuntimeException('Expected last queueId to be a string, got ' . gettype($lastQueueId));
        }

        $observeEventsOptions = new ObserveEventsOptions(
            recursive: true,
            lowerBound: new Bound(
                id: $lastQueueId,
                type: $lastQueueId === '0' ? BoundType::INCLUSIVE : BoundType::EXCLUSIVE,
            ),
        );

        foreach ($this->client->observeEvents('/flow/queue', $observeEventsOptions) as $event) {
            yield new ObserveItem(
                queueId: $event->id,
                type: $event->data['type'] ?? '',
                flowSource: $event->data['flowSource'] ?? '',
                flowHash: $event->data['flowHash'] ?? null,
                messageSource: $event->data['messageSource'] ?? '',
                message: $event->data['message'] ?? [],
                includeStubs: $event->data['includeStubs'] ?? [],
            );
        }
    }

    /**
     * @return iterable<array<mixed>>
     */
    public function findAllSchemas(): iterable
    {
        $schemaEvents = $this->client->readEvents(
            subject: '/flow/schema',
            readEventsOptions: new ReadEventsOptions(true),
        );

        foreach ($schemaEvents as $schemaEvent) {
            yield $schemaEvent->data;
        }
    }

    /**
     * @return iterable<ObserveItem >
     */
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable
    {
        $lastFlowRunWithQueueId = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_RUN . '" ' .
            'AND e.data.queueId != null ' .
            'ORDER BY e.id DESC ' .
            'TOP 1 ' .
            'PROJECT INTO e.data.queueId'
        );
        $lastFlowRunEvent = iterator_to_array($lastFlowRunWithQueueId);
        $lastQueueId = $lastFlowRunEvent[0] ?? '0';

        if (!is_string($lastQueueId)) {
            return;
        }

        $events = $this->client->readEvents(
            self::QUEUE_SUBJECT,
            new ReadEventsOptions(
                lowerBound: new Bound(
                    id: $lastQueueId,
                    type: $lastQueueId === '0' ? BoundType::INCLUSIVE : BoundType::EXCLUSIVE,
                ),
            ),
        );

        $allEvents = iterator_to_array($events);
        if ($sortEnum === SortEnum::DESC) {
            $allEvents = array_reverse($allEvents);
        }

        foreach ($allEvents as $allEvent) {
            yield new ObserveItem(
                queueId: $allEvent->id,
                type: $allEvent->data['type'] ?? '',
                flowSource: $allEvent->data['flowSource'] ?? '',
                flowHash: $allEvent->data['flowHash'] ?? null,
                messageSource: $allEvent->data['messageSource'] ?? '',
                message: $allEvent->data['message'] ?? [],
                includeStubs: $allEvent->data['includeStubs'] ?? [],
            );
        }
    }

    public function countFlows(): int
    {
        $flowEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_INSTANCE . '" ' .
            'PROJECT INTO COUNT()'
        );
        $flowEvents = iterator_to_array($flowEvents);
        return $flowEvents[0] ?? 0;
    }

    public function countFlowsBySource(string $flowSource = ''): int
    {
        $flowEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_INSTANCE . '" ' .
            'AND e.data.flowSource == "' . $flowSource . '" ' .
            'PROJECT INTO COUNT()'
        );
        $flowEvents = iterator_to_array($flowEvents);
        return $flowEvents[0] ?? 0;
    }

    public function countFlowsByType(string $flowType = ''): int
    {
        $flowEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_INSTANCE . '" ' .
            'AND STARTSWITH(e.data.flowType, "' . $flowType . '.v") ' .
            'PROJECT INTO COUNT()'
        );
        $flowEvents = iterator_to_array($flowEvents);
        return $flowEvents[0] ?? 0;
    }

    public function countExceptions(): int
    {
        $flowEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_EXCEPTION . '" ' .
            'PROJECT INTO COUNT()'
        );
        $flowEvents = iterator_to_array($flowEvents);
        return $flowEvents[0] ?? 0;
    }

    public function countExceptionsByFlowHash(string $flowHash = ''): int
    {
        $flowEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_EXCEPTION . '" ' .
            'AND e.data.flowHash == "' . $flowHash . '" ' .
            'PROJECT INTO COUNT()'
        );
        $flowEvents = iterator_to_array($flowEvents);
        return $flowEvents[0] ?? 0;
    }

    /**
     * @return FlowEntity[]
     * @throws Exception
     */
    public function findAllFlows(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        $skip = max(0, $skip);
        $top = max(1, $top);

        $timeFilter = '';
        if ($from instanceof DateTimeInterface) {
            $timeFilter .= 'AND e.data.time AS DATETIME >= "' . $from->format(DateTimeInterface::RFC3339_EXTENDED) . '" AS DATETIME ';
        }

        if ($to instanceof DateTimeInterface) {
            $timeFilter .= 'AND e.data.time AS DATETIME <= "' . $to->format(DateTimeInterface::RFC3339_EXTENDED) . '" AS DATETIME ';
        }

        $flowEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_INSTANCE . '" ' .
            $timeFilter .
            'ORDER BY e.id ' . $sortEnum->name . ' ' .
            'SKIP ' . $skip . ' ' .
            'TOP ' . $top . ' ' .
            'PROJECT INTO e.data'
        );

        foreach ($flowEvents as $flowEvent) {
            /** @var array{flowHash: string, flowType: string, flowSource: string, flowSubject: string, time: string} $flowEvent */
            yield new FlowEntity(
                flowHash: $flowEvent['flowHash'],
                flowType: $flowEvent['flowType'],
                flowSource: $flowEvent['flowSource'],
                flowSubject: $flowEvent['flowSubject'],
                time: new DateTimeImmutable($flowEvent['time']),
                exceptionCount: $this->countExceptionsByFlowHash($flowEvent['flowHash']),
            );
        }
    }

    /**
     * @return FlowEntity[]
     * @throws Exception
     */
    public function findFlowsBySource(string $flowSource, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        $skip = max(0, $skip);
        $top = max(1, $top);

        $timeFilter = '';
        if ($from instanceof DateTimeInterface) {
            $timeFilter .= 'AND e.data.time AS DATETIME >= "' . $from->format(DateTimeInterface::RFC3339_EXTENDED) . '" AS DATETIME ';
        }

        if ($to instanceof DateTimeInterface) {
            $timeFilter .= 'AND e.data.time AS DATETIME <= "' . $to->format(DateTimeInterface::RFC3339_EXTENDED) . '" AS DATETIME ';
        }

        $flowEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_INSTANCE . '" ' .
            'AND e.data.flowSource == "' . $flowSource . '" ' .
            $timeFilter .
            'ORDER BY e.id ' . $sortEnum->name . ' ' .
            'SKIP ' . $skip . ' ' .
            'TOP ' . $top . ' ' .
            'PROJECT INTO e.data'
        );

        foreach ($flowEvents as $flowEvent) {
            /** @var array{flowHash: string, flowType: string, flowSource: string, flowSubject: string, time: string} $flowEvent */
            yield new FlowEntity(
                flowHash: $flowEvent['flowHash'],
                flowType: $flowEvent['flowType'],
                flowSource: $flowEvent['flowSource'],
                flowSubject: $flowEvent['flowSubject'],
                time: new DateTimeImmutable($flowEvent['time']),
                exceptionCount: $this->countExceptionsByFlowHash($flowEvent['flowHash']),
            );
        }
    }

    public function findFlowsByType(string $flowType, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        $skip = max(0, $skip);
        $top = max(1, $top);

        $timeFilter = '';
        if ($from instanceof DateTimeInterface) {
            $timeFilter .= 'AND e.data.time AS DATETIME >= "' . $from->format(DateTimeInterface::RFC3339_EXTENDED) . '" AS DATETIME ';
        }

        if ($to instanceof DateTimeInterface) {
            $timeFilter .= 'AND e.data.time AS DATETIME <= "' . $to->format(DateTimeInterface::RFC3339_EXTENDED) . '" AS DATETIME ';
        }

        $flowEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_INSTANCE . '" ' .
            'AND STARTSWITH(e.data.flowType, "' . $flowType . '.v") ' .
            $timeFilter .
            'ORDER BY e.id ' . $sortEnum->name . ' ' .
            'SKIP ' . $skip . ' ' .
            'TOP ' . $top . ' ' .
            'PROJECT INTO e.data'
        );

        foreach ($flowEvents as $flowEvent) {
            /** @var array{flowHash: string, flowType: string, flowSource: string, flowSubject: string, time: string} $flowEvent */
            yield new FlowEntity(
                flowHash: $flowEvent['flowHash'],
                flowType: $flowEvent['flowType'],
                flowSource: $flowEvent['flowSource'],
                flowSubject: $flowEvent['flowSubject'],
                time: new DateTimeImmutable($flowEvent['time']),
                exceptionCount: $this->countExceptionsByFlowHash($flowEvent['flowHash']),
            );
        }
    }

    /**
     * @return FlowException[]
     * @throws Exception
     */
    public function findAllExceptions(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        $skip = max(0, $skip);
        $top = max(1, $top);

        $timeFilter = '';
        if ($from instanceof DateTimeInterface) {
            $timeFilter .= 'AND e.data.time AS DATETIME >= "' . $from->format(DateTimeInterface::RFC3339_EXTENDED) . '" AS DATETIME ';
        }

        if ($to instanceof DateTimeInterface) {
            $timeFilter .= 'AND e.data.time AS DATETIME <= "' . $to->format(DateTimeInterface::RFC3339_EXTENDED) . '" AS DATETIME ';
        }

        $exceptionEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_EXCEPTION . '" ' .
            $timeFilter .
            'ORDER BY e.id ' . $sortEnum->name . ' ' .
            'SKIP ' . $skip . ' ' .
            'TOP ' . $top . ' ' .
            'PROJECT INTO e.data'
        );

        foreach ($exceptionEvents as $exceptionEvent) {
            /** @var array{flowHash: string, flowRuntimeHash: string, flowType: string, stubSource: string, stubHash: ?string, code: int, message: string, file: string, line: int, traceString: string, time: string, hash: string} $exceptionEvent */
            yield new FlowException(
                flowHash: $exceptionEvent['flowHash'],
                flowRuntimeHash: $exceptionEvent['flowRuntimeHash'],
                flowType: $exceptionEvent['flowType'],
                stubSource: $exceptionEvent['stubSource'],
                stubHash: $exceptionEvent['stubHash'],
                code: $exceptionEvent['code'],
                message: $exceptionEvent['message'],
                file: $exceptionEvent['file'],
                line: $exceptionEvent['line'],
                traceString: $exceptionEvent['traceString'],
                time: new DateTimeImmutable($exceptionEvent['time']),
                hash: $exceptionEvent['hash'],
            );
        }
    }

    /**
     * @return FlowException[]
     * @throws Exception
     */
    public function findExceptionsByFlowHash(string $flowHash, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        $skip = max(0, $skip);
        $top = max(1, $top);

        $timeFilter = '';
        if ($from instanceof DateTimeInterface) {
            $timeFilter .= 'AND e.data.time AS DATETIME >= "' . $from->format(DateTimeInterface::RFC3339_EXTENDED) . '" AS DATETIME ';
        }

        if ($to instanceof DateTimeInterface) {
            $timeFilter .= 'AND e.data.time AS DATETIME <= "' . $to->format(DateTimeInterface::RFC3339_EXTENDED) . '" AS DATETIME ';
        }

        $exceptionEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_EXCEPTION . '" ' .
            'AND e.data.flowHash == "' . $flowHash . '" ' .
            $timeFilter .
            'ORDER BY e.id ' . $sortEnum->name . ' ' .
            'SKIP ' . $skip . ' ' .
            'TOP ' . $top . ' ' .
            'PROJECT INTO e.data'
        );

        foreach ($exceptionEvents as $exceptionEvent) {
            /** @var array{flowHash: string, flowRuntimeHash: string, flowType: string, stubSource: string, stubHash: ?string, code: int, message: string, file: string, line: int, traceString: string, time: string, hash: string} $exceptionEvent */
            yield new FlowException(
                flowHash: $exceptionEvent['flowHash'],
                flowRuntimeHash: $exceptionEvent['flowRuntimeHash'],
                flowType: $exceptionEvent['flowType'],
                stubSource: $exceptionEvent['stubSource'],
                stubHash: $exceptionEvent['stubHash'],
                code: $exceptionEvent['code'],
                message: $exceptionEvent['message'],
                file: $exceptionEvent['file'],
                line: $exceptionEvent['line'],
                traceString: $exceptionEvent['traceString'],
                time: new DateTimeImmutable($exceptionEvent['time']),
                hash: $exceptionEvent['hash'],
            );
        }
    }

    public function findFlowByHash(string $flowHash): ?Flow
    {
        if ($flowHash === '') {
            return null;
        }

        $flowArray = [];
        $flowEvents = $this->client->readEvents(
            subject: '/flow/' . $flowHash,
            readEventsOptions: new ReadEventsOptions(
                recursive: true,
                order: Order::CHRONOLOGICAL,
            ),
        );

        foreach ($flowEvents as $flowEvent) {
            if ($flowEvent->type === self::TYPE_INSTANCE) {
                $flowArray = $flowEvent->data;
            }

            if ($flowEvent->type === self::TYPE_MESSAGE) {
                $flowArray['flowMessages'][] = $flowEvent->data;
            }

            if ($flowEvent->type === self::TYPE_EXCEPTION) {
                $flowArray['flowExceptions'][] = $flowEvent->data;
            }

            if ($flowEvent->type === self::TYPE_RESULT) {
                $flowArray['flowResults'][] = $flowEvent->data;
            }

            if ($flowEvent->type === self::TYPE_RUN) {
                $flowArray['flowRuns'][] = $flowEvent->data;
            }
        }

        if ($flowArray === []) {
            return null;
        }

        $flowEvents = $this->client->readEvents(
            subject: '/flow/schema/' . $flowArray['flowSchemaHash'],
            readEventsOptions: new ReadEventsOptions(recursive: false)
        );
        foreach ($flowEvents as $flowEvent) {
            $flowArray['flowSchema'] = $flowEvent->data;
        }

        return Converter::arrayToFlow($flowArray);
    }

    public function findFlowByRuntimeHash(string $flowRuntimeHash): ?Flow
    {
        $flowHashIter = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_RUN . '" ' .
            'AND e.data.flowRuntimeHash == "' . $flowRuntimeHash . '" ' .
            'PROJECT INTO e.data.flowHash'
        );

        $flowHash = iterator_to_array($flowHashIter)[0] ?? '';

        return $this->findFlowByHash($flowHash);
    }

    public function findStubSourceByHash(string $stubHash): ?StubSourceEntity
    {
        if ($stubHash === '') {
            return null;
        }

        $stubSourceEvents = $this->client->readEvents(
            subject: '/flow/source/stub/' . $stubHash,
            readEventsOptions: new ReadEventsOptions(recursive: false)
        );
        $stubSourceEvent = iterator_to_array($stubSourceEvents)[0] ?? null;

        if ($stubSourceEvent === null) {
            return null;
        }

        /** @var array{stubHash: string, stubSource: class-string, sourceContent: string, time: string} $data */
        $data = $stubSourceEvent->data;
        return new StubSourceEntity(
            stubHash: $data['stubHash'],
            stubSource: $data['stubSource'],
            sourceContent: $data['sourceContent'],
            time: new DateTimeImmutable($data['time']),
        );
    }

    /**
     * @param class-string $stubSource
     * @return iterable<StubSourceEntity>
     */
    public function findStubSourcesByStubSource(string $stubSource): iterable
    {
        $stubSourceEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_SOURCE_STUB . '" ' .
            'AND e.data.stubSource == "' . $stubSource . '" ' .
            'ORDER by e.id ASC ' .
            'PROJECT INTO e.data'
        );

        foreach ($stubSourceEvents as $stubSourceEvent) {
            /** @var array{stubHash: string, stubSource: class-string, sourceContent: string, time: string} $stubSourceEvent */
            yield new StubSourceEntity(
                stubHash: $stubSourceEvent['stubHash'],
                stubSource: $stubSourceEvent['stubSource'],
                sourceContent: $stubSourceEvent['sourceContent'],
                time: new DateTimeImmutable($stubSourceEvent['time']),
            );
        }
    }
}
