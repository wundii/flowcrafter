<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use DateTimeImmutable;
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
use Thenativeweb\Eventsourcingdb\ReadEventsOptions;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Converter;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;

class Esdb implements StorageInterface
{
    public const SOURCE = 'https://flowcrafter';

    public const QUEUE_SUBJECT = '/flow/queue';

    public const TYPE_INSTANCE = 'flowcrafter.flow.instance.v1';

    public const TYPE_MESSAGE = 'flowcrafter.flow.message.v1';

    public const TYPE_EXCEPTION = 'flowcrafter.flow.exception.v1';

    public const TYPE_QUEUE = 'flowcrafter.flow.queue.v1';

    public const TYPE_RUN = 'flowcrafter.flow.run.v1';

    public const TYPE_SCHEMA = 'flowcrafter.flow.schema.v1';

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
                    'messageType',
                    'messageSource',
                    'time',
                    'hash',
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
                    'stubSource' => [
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
                    'stubSource',
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
            ]
        );
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
        unset($data['flowRuns']);

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
                'time' => $flow->getTime()->format(DATE_ATOM),
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

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     */
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, array $message): void
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
            'WHERE e.subject == "' . self::QUEUE_SUBJECT . '" ' .
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
            );
        }
    }

    /**
     * @return FlowEntity[]
     * @throws Exception
     */
    public function findAllFlows(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000): iterable
    {
        $flowEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_INSTANCE . '" ' .
            'ORDER BY e.id ' . $sortEnum->name . ' ' .
            'TOP ' . $top . ' ' .
            'PROJECT INTO e.data'
        );

        foreach ($flowEvents as $flowEvent) {
            yield new FlowEntity(
                flowHash: $flowEvent['flowHash'] ?? '',
                flowType: $flowEvent['flowType'] ?? '',
                flowSource: $flowEvent['flowSource'] ?? '',
                flowSubject: $flowEvent['flowSubject'] ?? '',
                time: new DateTimeImmutable($flowEvent['time'] ?? 'now'),
            );
        }
    }

    /**
     * @return FlowEntity[]
     * @throws Exception
     */
    public function findFlowsBySource(string $flowSource, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000): iterable
    {
        $flowEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_INSTANCE . '" ' .
            'AND e.data.flowSource == "' . $flowSource . '" ' .
            'ORDER BY e.id ' . $sortEnum->name . ' ' .
            'TOP ' . $top . ' ' .
            'PROJECT INTO e.data'
        );

        foreach ($flowEvents as $flowEvent) {
            yield new FlowEntity(
                flowHash: $flowEvent['flowHash'] ?? '',
                flowType: $flowEvent['flowType'] ?? '',
                flowSource: $flowEvent['flowSource'] ?? '',
                flowSubject: $flowEvent['flowSubject'] ?? '',
                time: new DateTimeImmutable($flowEvent['time'] ?? 'now'),
            );
        }
    }

    /**
     * @return FlowException[]
     * @throws Exception
     */
    public function findAllExceptions(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000): iterable
    {
        $exceptionEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_EXCEPTION . '" ' .
            'ORDER BY e.id ' . $sortEnum->name . ' ' .
            'TOP ' . $top . ' ' .
            'PROJECT INTO e.data'
        );

        foreach ($exceptionEvents as $exceptionEvent) {
            yield new FlowException(
                flowHash: $exceptionEvent['flowHash'] ?? '',
                flowRuntimeHash: $exceptionEvent['flowRuntimeHash'] ?? '',
                stubSource: $exceptionEvent['stubSource'] ?? '',
                code: $exceptionEvent['code'] ?? 0,
                message: $exceptionEvent['message'] ?? '',
                file: $exceptionEvent['file'] ?? '',
                line: $exceptionEvent['line'] ?? 0,
                traceString: $exceptionEvent['traceString'] ?? '',
                time: new DateTimeImmutable($exceptionEvent['time'] ?? 'now'),
                hash: $exceptionEvent['hash'] ?? '',
            );
        }
    }

    /**
     * @return FlowException[]
     * @throws Exception
     */
    public function findExceptionsByFlowHash(string $flowHash, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000): iterable
    {
        $exceptionEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_EXCEPTION . '" ' .
            'AND e.data.flowHash == "' . $flowHash . '" ' .
            'ORDER BY e.id ' . $sortEnum->name . ' ' .
            'TOP ' . $top . ' ' .
            'PROJECT INTO e.data'
        );

        foreach ($exceptionEvents as $exceptionEvent) {
            yield new FlowException(
                flowHash: $exceptionEvent['flowHash'] ?? '',
                flowRuntimeHash: $exceptionEvent['flowRuntimeHash'] ?? '',
                stubSource: $exceptionEvent['stubSource'] ?? '',
                code: $exceptionEvent['code'] ?? 0,
                message: $exceptionEvent['message'] ?? '',
                file: $exceptionEvent['file'] ?? '',
                line: $exceptionEvent['line'] ?? 0,
                traceString: $exceptionEvent['traceString'] ?? '',
                time: new DateTimeImmutable($exceptionEvent['time'] ?? 'now'),
                hash: $exceptionEvent['hash'] ?? '',
            );
        }
    }

    public function findFlowByHash(string $flowHash): ?Flow
    {
        $flowArray = [];
        $flowEvents = $this->client->readEvents('/flow/' . $flowHash, new ReadEventsOptions(true));

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

            if ($flowEvent->type === self::TYPE_RUN) {
                $flowArray['flowRuns'][] = $flowEvent->data;
            }
        }

        if ($flowArray === []) {
            return null;
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

        $flowArray = [];
        $flowEvents = $this->client->readEvents('/flow/' . $flowHash, new ReadEventsOptions());

        foreach ($flowEvents as $flowEvent) {
            if ($flowEvent->type === self::TYPE_INSTANCE) {
                $flowArray = $flowEvent->data;
                break;
            }
        }

        if ($flowArray === []) {
            return null;
        }

        $messageEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_MESSAGE . '" ' .
            'AND e.data.flowRuntimeHash == "' . $flowRuntimeHash . '" ' .
            'PROJECT INTO e.data'
        );

        foreach ($messageEvents as $messageEvent) {
            $flowArray['flowMessages'][] = $messageEvent;
        }

        $exceptionEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_EXCEPTION . '" ' .
            'AND e.data.flowRuntimeHash == "' . $flowRuntimeHash . '" ' .
            'PROJECT INTO e.data'
        );

        foreach ($exceptionEvents as $exceptionEvent) {
            $flowArray['flowExceptions'][] = $exceptionEvent;
        }

        $runEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_RUN . '" ' .
            'AND e.data.flowRuntimeHash == "' . $flowRuntimeHash . '" ' .
            'PROJECT INTO e.data'
        );

        foreach ($runEvents as $runEvent) {
            $flowArray['flowRuns'][] = $runEvent;
        }

        return Converter::arrayToFlow($flowArray);
    }
}
