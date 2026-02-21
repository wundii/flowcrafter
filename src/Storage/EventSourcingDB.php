<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use Thenativeweb\Eventsourcingdb\Client;
use Thenativeweb\Eventsourcingdb\EventCandidate;
use Thenativeweb\Eventsourcingdb\IsSubjectPopulated;
use Thenativeweb\Eventsourcingdb\IsSubjectPristine;
use Thenativeweb\Eventsourcingdb\ReadEventsOptions;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\StorageInterface;

class EventSourcingDB implements StorageInterface
{
    public const SOURCE = 'https://flowcrafter';

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

    public function initialize(): void
    {
        $eventTypesQl = 'FROM e IN eventtypes PROJECT INTO e';
        $eventTypes = $this->client->runEventQlQuery($eventTypesQl);
        $eventTypes = iterator_to_array($eventTypes);

        if (!in_array('io.flowcrafter.flow.v1', $eventTypes, true)) {
            $eventSchema = 'io.flowcrafter.flow.v1';
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
                    'flowRuntimeHash' => [
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
                    'flowRuntimeHash',
                    'time',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventSchema, $registerEventSchema);
        }

        if (!in_array('io.flowcrafter.flow.schema.v1', $eventTypes, true)) {
            $eventSchema = 'io.flowcrafter.flow.schema.v1';
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

            $this->client->registerEventSchema($eventSchema, $registerEventSchema);
        }

        if (!in_array('io.flowcrafter.flow.message.v1', $eventTypes, true)) {
            $eventSchema = 'io.flowcrafter.flow.message.v1';
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
                    'predecessorHash',
                ],
                'additionalProperties' => false,
            ];

            $this->client->registerEventSchema($eventSchema, $registerEventSchema);
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
            type: 'io.flowcrafter.flow.schema.v1',
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

    public function writeFlow(Flow $flow): void
    {
        $data = $flow->jsonSerialize();
        unset($data['flowMessages']);

        $subject = '/flow/' . $flow->getHash();
        $subjectSchema = '/flow/schema/' . $flow->getSchema()->getHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: 'io.flowcrafter.flow.v1',
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

    public function writeFlowMessage(FlowMessage $flowMessage): void
    {
        $subject = '/flow/message/' . $flowMessage->getHash();
        $subjectFlow = '/flow/' . $flowMessage->getFlowHash();
        $eventCandidate = new EventCandidate(
            source: self::SOURCE,
            subject: $subject,
            type: 'io.flowcrafter.flow.message.v1',
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
}
