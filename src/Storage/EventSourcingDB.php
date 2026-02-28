<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

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
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\ObserveItem;

class EventSourcingDB implements StorageInterface
{
    public const SOURCE = 'https://flowcrafter';

    public const QUEUE_SUBJECT = '/flow/queue';

    private Client $client;

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

        if (!in_array('flowcrafter.flow.instance.v1', $eventTypes, true)) {
            $eventType = 'flowcrafter.flow.instance.v1';
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
                    'flowSchema' => [
                        'type' => 'object',
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

        if (!in_array('flowcrafter.flow.run.v1', $eventTypes, true)) {
            $eventType = 'flowcrafter.flow.run.v1';
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

        if (!in_array('flowcrafter.flow.schema.v1', $eventTypes, true)) {
            $eventType = 'flowcrafter.flow.schema.v1';
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

        if (!in_array('flowcrafter.flow.message.v1', $eventTypes, true)) {
            $eventType = 'flowcrafter.flow.message.v1';
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

        if (!in_array('flowcrafter.flow.queue.v1', $eventTypes, true)) {
            $eventType = 'flowcrafter.flow.queue.v1';
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
                        'type' => 'string',
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

    public function registeredFlowSchema(FlowSchema $flowSchema): void
    {
        $subject = '/flow/schema/' . $flowSchema->getHash();

        $readEventsOptions = new ReadEventsOptions(false);
        if (iterator_to_array($this->client->readEvents($subject, $readEventsOptions)) !== []) {
            return;
        }

        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: 'flowcrafter.flow.schema.v1',
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

    public function registeredFlow(Flow $flow): void
    {
        $subject = '/flow/' . $flow->getHash();

        $readEventsOptions = new ReadEventsOptions(false);
        if (iterator_to_array($this->client->readEvents($subject, $readEventsOptions)) !== []) {
            return;
        }

        $data = $flow->jsonSerialize();
        unset($data['flowMessages']);

        $subjectSchema = '/flow/schema/' . $flow->getSchema()->getHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: 'flowcrafter.flow.instance.v1',
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

    public function writeFlow(Flow $flow, ?string $queueId = null): void
    {
        $subject = '/flow/' . $flow->getHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: 'flowcrafter.flow.run.v1',
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
                new IsEventQlQueryTrue('FROM e IN events WHERE e.subject == "' . $subject . '" AND e.type == "flowcrafter.flow.instance.v1" PROJECT INTO COUNT() == 1'),
            ]
        );
    }

    public function writeFlowMessage(FlowMessage $flowMessage): void
    {
        $subject = '/flow/message/' . $flowMessage->getHash();
        $subjectFlow = '/flow/' . $flowMessage->getFlowHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: 'flowcrafter.flow.message.v1',
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

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     */
    public function addObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, array $message): void
    {
        Assert::classString($flowSource, FlowInterface::class);
        Assert::classString($messageSource, MessageInterface::class);

        $this->client->writeEvents([
            new EventCandidate(
                source: self::SOURCE,
                subject: self::QUEUE_SUBJECT,
                type: 'flowcrafter.flow.queue.v1',
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

        $query = 'FROM e IN events WHERE e.subject == "' . self::QUEUE_SUBJECT . '" AND e.data.queueId != null ORDER BY e.id DESC PROJECT INTO e.data.queueId';

        $lastFlowRunWithQueueId = $this->client->runEventQlQuery($query);
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
}
