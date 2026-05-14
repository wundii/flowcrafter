<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use DateTimeImmutable;
use DateTimeInterface;
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
use Throwable;
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
use Wundii\Flowcrafter\Interface\StepInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\ObserverException;
use Wundii\Flowcrafter\Schedule\ScheduleException;
use Wundii\Flowcrafter\Storage\Config\EsdbConfig;
use Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowSchemaEntity;
use Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity;
use Wundii\Flowcrafter\Storage\Entity\StepSourceEntity;

class Esdb extends Service
{
    public const SOURCE = 'https://flowcrafter';

    public const QUEUE_SUBJECT = '/flow/queue';

    public const TYPE_INSTANCE = 'flowcrafter.flow.instance.v1';

    public const TYPE_MESSAGE = 'flowcrafter.flow.message.v1';

    public const TYPE_EXCEPTION = 'flowcrafter.flow.exception.v1';

    public const TYPE_RESULT = 'flowcrafter.flow.result.v1';

    public const TYPE_QUEUE = 'flowcrafter.flow.queue.v1';

    public const TYPE_QUEUE_CLAIM = 'flowcrafter.flow.queue.claim.v1';

    public const TYPE_OBSERVER_EXCEPTION = 'flowcrafter.observer.exception.v1';

    public const TYPE_RUN = 'flowcrafter.flow.run.v1';

    public const TYPE_SCHEMA = 'flowcrafter.flow.schema.v1';

    public const TYPE_SOURCE_MESSAGE = 'flowcrafter.flow.source.message.v1';

    public const TYPE_SOURCE_STEP = 'flowcrafter.flow.source.step.v1';

    protected Client $client;

    public function __construct(EsdbConfig $esdbConfig, ?string $sqliteFile = null)
    {
        parent::__construct($sqliteFile);

        $this->client = new Client(
            $esdbConfig->getUrl(),
            $esdbConfig->getApiToken(),
        );
    }

    public function isPrimaryStorageInitialized(): bool
    {
        try {
            $eventTypesQl = 'FROM e IN eventtypes PROJECT INTO e';
            $eventTypes = $this->client->runEventQlQuery($eventTypesQl);
            $eventTypes = iterator_to_array($eventTypes);

            return in_array(self::TYPE_INSTANCE, $eventTypes, true);
        } catch (Throwable) {
            return false;
        }
    }

    public function initializeDatabase(): void
    {
        parent::initializeDatabase();

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
                    'flowType' => [
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
                    'flowType',
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
                    'steps' => [
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
                    'steps',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventType, $registerEventSchema);
        }

        if (!in_array(self::TYPE_SOURCE_MESSAGE, $eventTypes, true)) {
            $eventType = self::TYPE_SOURCE_MESSAGE;
            $registerEventSchema = [
                'type' => 'object',
                'properties' => [
                    'messageHash' => [
                        'type' => 'string',
                    ],
                    'messageSource' => [
                        'type' => 'string',
                    ],
                    'propertyNames' => [
                        'type' => 'object',
                    ],
                    'time' => [
                        'type' => 'string',
                    ],
                ],
                'required' => [
                    'messageHash',
                    'messageSource',
                    'propertyNames',
                    'time',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventType, $registerEventSchema);
        }

        if (!in_array(self::TYPE_SOURCE_STEP, $eventTypes, true)) {
            $eventType = self::TYPE_SOURCE_STEP;
            $registerEventSchema = [
                'type' => 'object',
                'properties' => [
                    'stepHash' => [
                        'type' => 'string',
                    ],
                    'stepSource' => [
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
                    'stepHash',
                    'stepSource',
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
                    'stepSource' => [
                        'type' => 'string',
                    ],
                    'stepHash' => [
                        'type' => 'string',
                    ],
                    'messageHash' => [
                        'type' => 'string',
                    ],
                    'messageType' => [
                        'type' => 'string',
                    ],
                    'messageSource' => [
                        'type' => 'string',
                    ],
                    'message' => [
                        'type' => ['null', 'object'],
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
                    'stepSource',
                    'stepHash',
                    'messageHash',
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
                    'stepSource' => [
                        'type' => 'string',
                    ],
                    'stepHash' => [
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
                    'stepSource',
                    'stepHash',
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
                    'stepSource' => [
                        'type' => 'string',
                    ],
                    'stepHash' => [
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
                    'stepSource',
                    'stepHash',
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
                    'flowSubject' => [
                        'type' => ['null', 'string'],
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
                        'type' => ['null', 'array', 'object'],
                    ],
                    'includeSteps' => [
                        'type' => ['array'],
                    ],
                ],
                'required' => [
                    'type',
                    'flowSubject',
                    'flowSource',
                    'flowHash',
                    'messageSource',
                    'message',
                    'includeSteps',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventType, $registerEventSchema);
        }

        if (!in_array(self::TYPE_QUEUE_CLAIM, $eventTypes, true)) {
            $eventType = self::TYPE_QUEUE_CLAIM;
            $registerEventSchema = [
                'type' => 'object',
                'properties' => [
                    'eventId' => [
                        'type' => 'string',
                    ],
                ],
                'required' => [
                    'eventId',
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

    public function registerMessageSource(MessageSourceEntity $messageSourceEntity): void
    {
        $subject = '/flow/source/message/' . $messageSourceEntity->messageHash;

        $readEventsOptions = new ReadEventsOptions(false);
        if (iterator_to_array($this->client->readEvents($subject, $readEventsOptions)) !== []) {
            return;
        }

        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: self::TYPE_SOURCE_MESSAGE,
            data: $messageSourceEntity->jsonSerialize(),
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

    public function registerStepSource(StepSourceEntity $stepSourceEntity): void
    {
        $subject = '/flow/source/step/' . $stepSourceEntity->stepHash;

        $readEventsOptions = new ReadEventsOptions(false);
        if (iterator_to_array($this->client->readEvents($subject, $readEventsOptions)) !== []) {
            return;
        }

        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: self::TYPE_SOURCE_STEP,
            data: $stepSourceEntity->jsonSerialize(),
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
        unset($data['flowResults']);
        unset($data['flowRuns']);
        unset($data['flowStatus']);
        unset($data['isExecutable']);
        unset($data['isReadOnly']);
        unset($data['readOnlyReasons']);

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
                'flowType' => $flow->getType(),
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

    public function appendScheduleException(ScheduleException $scheduleException): void
    {
        $subject = '/schedule/exception/' . $scheduleException->getHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: 'flowcrafter.schedule.exception.v1',
            data: $scheduleException->jsonSerialize(),
        );

        $this->client->writeEvents(
            [$eventCandidate],
            [new IsSubjectPristine($subject)],
        );

        parent::appendScheduleException($scheduleException);
    }

    public function appendObserverException(ObserverException $observerException): void
    {
        $subject = '/observer/exception/' . $observerException->getHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: self::TYPE_OBSERVER_EXCEPTION,
            data: $observerException->jsonSerialize(),
        );

        $this->client->writeEvents(
            [$eventCandidate],
            [new IsSubjectPristine($subject)],
        );

        parent::appendObserverException($observerException);
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

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     */
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, ?array $message, array $includeSteps = [], ?string $flowSubject = null): void
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
                    'includeSteps' => $includeSteps,
                    'flowSubject' => $flowSubject,
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

        $lowerBound = $this->resolveQueueLowerBound();

        $observeEventsOptions = new ObserveEventsOptions(
            recursive: false,
            lowerBound: $lowerBound,
        );

        foreach ($this->client->observeEvents(self::QUEUE_SUBJECT, $observeEventsOptions) as $event) {
            if (!$this->claimQueueItem($event->id)) {
                continue;
            }

            yield new ObserveItem(
                queueId: $event->id,
                type: $event->data['type'] ?? '',
                flowSubject: $event->data['flowSubject'] ?? null,
                flowSource: $event->data['flowSource'] ?? '',
                flowHash: $event->data['flowHash'] ?? null,
                messageSource: $event->data['messageSource'] ?? '',
                message: $event->data['message'] ?? null,
                includeSteps: $event->data['includeSteps'] ?? [],
            );
        }
    }

    public function openQueues(): int
    {
        $lowerBound = $this->resolveQueueLowerBound();

        $events = $this->client->readEvents(
            self::QUEUE_SUBJECT,
            new ReadEventsOptions(
                lowerBound: $lowerBound,
            ),
        );

        return count(iterator_to_array($events));
    }

    /**
     * @return iterable<string>
     */
    public function findAllFlowHashes(): iterable
    {
        $query = 'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_INSTANCE . '" ' .
            'PROJECT INTO e.data.flowHash';

        foreach ($this->client->runEventQlQuery($query) as $flowHash) {
            yield $flowHash;
        }
    }

    /**
     * @return iterable<FlowSchemaEntity>
     */
    public function findAllSchemas(): iterable
    {
        $schemaEvents = $this->client->readEvents(
            subject: '/flow/schema',
            readEventsOptions: new ReadEventsOptions(true),
        );

        foreach ($schemaEvents as $schemaEvent) {
            yield new FlowSchemaEntity(
                basename($schemaEvent->subject),
                $schemaEvent->data['type'],
                $schemaEvent->data['steps'],
            );
        }
    }

    /**
     * @return iterable<MessageSourceEntity>
     */
    public function findAllMessageSources(): iterable
    {
        $messageSourceEvents = $this->client->readEvents(
            subject: '/flow/source/message',
            readEventsOptions: new ReadEventsOptions(true),
        );

        foreach ($messageSourceEvents as $messageSourceEvent) {
            yield new MessageSourceEntity(
                messageHash: $messageSourceEvent->data['messageHash'],
                messageSource: $messageSourceEvent->data['messageSource'],
                propertyNames: $messageSourceEvent->data['propertyNames'],
                time: new DateTimeImmutable($messageSourceEvent->data['time'] ?? '' ? $messageSourceEvent->data['time'] : 'now'),
            );
        }
    }

    /**
     * @return iterable<ObserveItem >
     */
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable
    {
        $lowerBound = $this->resolveQueueLowerBound();

        $events = $this->client->readEvents(
            self::QUEUE_SUBJECT,
            new ReadEventsOptions(
                lowerBound: $lowerBound,
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
                flowSubject: $allEvent->data['flowSubject'] ?? null,
                flowSource: $allEvent->data['flowSource'] ?? '',
                flowHash: $allEvent->data['flowHash'] ?? null,
                messageSource: $allEvent->data['messageSource'] ?? '',
                message: $allEvent->data['message'] ?? [],
                includeSteps: $allEvent->data['includeSteps'] ?? [],
            );
        }
    }

    public function findFlowInstanceByHash(string $flowHash): ?FlowInstanceEntity
    {
        if ($flowHash === '') {
            return null;
        }

        $query = 'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_INSTANCE . '" ' .
            'AND e.subject == "/flow/' . $flowHash . '" ' .
            'PROJECT INTO e.data';

        foreach ($this->client->runEventQlQuery($query) as $flowEvent) {
            return new FlowInstanceEntity(
                flowHash: $flowEvent['flowHash'] ?? '',
                flowType: $flowEvent['flowType'] ?? '',
                flowSource: $flowEvent['flowSource'] ?? '',
                flowSubject: $flowEvent['flowSubject'] ?? null,
                flowSchemaHash: $flowEvent['flowSchemaHash'] ?? '',
                time: new DateTimeImmutable($flowEvent['time'] ?? '' ? $flowEvent['time'] : 'now'),
            );
        }

        return null;
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
            'TOP 1 ' .
            'PROJECT INTO e.data.flowHash'
        );

        $flowHash = iterator_to_array($flowHashIter)[0] ?? '';

        return $this->findFlowByHash($flowHash);
    }

    public function findStepSourceByHash(string $stepHash): ?StepSourceEntity
    {
        if ($stepHash === '') {
            return null;
        }

        $stepSourceEvents = $this->client->readEvents(
            subject: '/flow/source/step/' . $stepHash,
            readEventsOptions: new ReadEventsOptions(recursive: false)
        );
        $stepSourceEvent = iterator_to_array($stepSourceEvents)[0] ?? null;

        if ($stepSourceEvent === null) {
            return null;
        }

        /** @var array{stepHash: string, stepSource: class-string<StepInterface>, sourceContent: string, time: string} $data */
        $data = $stepSourceEvent->data;
        return new StepSourceEntity(
            stepHash: $data['stepHash'],
            stepSource: $data['stepSource'],
            sourceContent: $data['sourceContent'],
            time: new DateTimeImmutable($data['time']),
        );
    }

    /**
     * @param class-string $stepSource
     * @return iterable<StepSourceEntity>
     */
    public function findStepSourcesByStepSource(string $stepSource): iterable
    {
        $stepSourceEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_SOURCE_STEP . '" ' .
            'AND e.data.stepSource == "' . $stepSource . '" ' .
            'ORDER by e.id ASC ' .
            'PROJECT INTO e.data'
        );

        foreach ($stepSourceEvents as $stepSourceEvent) {
            /** @var array{stepHash: string, stepSource: class-string<StepInterface>, sourceContent: string, time: string} $stepSourceEvent */
            yield new StepSourceEntity(
                stepHash: $stepSourceEvent['stepHash'],
                stepSource: $stepSourceEvent['stepSource'],
                sourceContent: $stepSourceEvent['sourceContent'],
                time: new DateTimeImmutable($stepSourceEvent['time']),
            );
        }
    }

    /**
     * @param class-string $messageSource
     * @return iterable<MessageSourceEntity>
     */
    public function findMessageSourceByMessageSource(string $messageSource): iterable
    {
        $messageSourceEvents = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.type == "' . self::TYPE_SOURCE_MESSAGE . '" ' .
            'AND e.data.messageSource == "' . $messageSource . '" ' .
            'ORDER by e.id ASC ' .
            'PROJECT INTO e.data'
        );

        foreach ($messageSourceEvents as $messageSourceEvent) {
            $hash = $messageSourceEvent['messageHash'] ?? null;
            if (!is_string($hash)) {
                continue;
            }

            /** @var array<string, list<string>> $propertyNames */
            $propertyNames = is_array($messageSourceEvent['propertyNames'] ?? null) ? $messageSourceEvent['propertyNames'] : [];
            /** @var class-string<MessageInterface> $messageSourceClass */
            $messageSourceClass = $messageSource;
            yield new MessageSourceEntity(
                messageHash: $hash,
                messageSource: $messageSourceClass,
                propertyNames: $propertyNames,
                time: new DateTimeImmutable($messageSourceEvent['time'] ?? 'now'),
            );
        }
    }

    private function claimQueueItem(string $eventId): bool
    {
        $subject = self::QUEUE_SUBJECT . '/claim/' . $eventId;

        try {
            $this->client->writeEvents(
                [
                    new EventCandidate(
                        source: self::SOURCE,
                        subject: $subject,
                        type: self::TYPE_QUEUE_CLAIM,
                        data: [
                            'eventId' => $eventId,
                        ],
                    ),
                ],
                [
                    new IsSubjectPristine($subject),
                ]
            );

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function resolveQueueLowerBound(): ?Bound
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
        $lastQueueId = $lastFlowRunEvent[0] ?? null;

        return $lastQueueId !== null
            ? new Bound(id: $lastQueueId, type: BoundType::EXCLUSIVE)
            : null;
    }
}
