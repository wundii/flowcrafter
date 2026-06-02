<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Queue;

use RuntimeException;
use Thenativeweb\Eventsourcingdb\Bound;
use Thenativeweb\Eventsourcingdb\BoundType;
use Thenativeweb\Eventsourcingdb\Client;
use Thenativeweb\Eventsourcingdb\EventCandidate;
use Thenativeweb\Eventsourcingdb\IsSubjectPristine;
use Thenativeweb\Eventsourcingdb\ObserveEventsOptions;
use Thenativeweb\Eventsourcingdb\ReadEventsOptions;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowMessageReadonly;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Projection\ProjectionQueueItem;
use Wundii\Flowcrafter\Queue\Config\EsdbQueueConfig;

final class EsdbQueue implements QueueInterface
{
    public const SOURCE = 'https://flowcrafter';

    public const QUEUE_SUBJECT = '/flow/queue';

    public const TYPE_QUEUE = 'flowcrafter.flow.queue.v1';

    public const TYPE_QUEUE_CLAIM = 'flowcrafter.flow.queue.claim.v1';

    public const TYPE_PROJECTION_ITEM = 'flowcrafter.projection.item.v1';

    public const TYPE_PROJECTION_CHECKPOINT = 'flowcrafter.projection.checkpoint.v1';

    /**
     * Mirrors Esdb::TYPE_RUN. The observer queue lower bound is derived from the
     * flow-run events written by the storage; when queue and storage point at
     * the same EventSourcingDB this resolves the last consumed queue item.
     */
    public const TYPE_RUN = 'flowcrafter.flow.run.v1';

    private readonly Client $client;

    public function __construct(EsdbQueueConfig $esdbQueueConfig)
    {
        $this->client = new Client(
            $esdbQueueConfig->getUrl(),
            $esdbQueueConfig->getApiToken(),
        );
    }

    public function initializeQueue(): void
    {
        $eventTypesQl = 'FROM e IN eventtypes PROJECT INTO e';
        $eventTypes = iterator_to_array($this->client->runEventQlQuery($eventTypesQl));

        if (!in_array(self::TYPE_QUEUE, $eventTypes, true)) {
            $this->client->registerEventSchema(self::TYPE_QUEUE, [
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
            ]);
        }

        if (!in_array(self::TYPE_QUEUE_CLAIM, $eventTypes, true)) {
            $this->client->registerEventSchema(self::TYPE_QUEUE_CLAIM, [
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
            ]);
        }
    }

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     * @param class-string[] $includeSteps
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
     * @return iterable<ObserveItem>
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

    public function appendProjectionQueueItem(FlowMessage $flowMessage, string $handlerClass): void
    {
        $this->client->writeEvents([
            new EventCandidate(
                source: self::SOURCE,
                subject: $this->projectionQueueSubject($handlerClass),
                type: self::TYPE_PROJECTION_ITEM,
                data: $flowMessage->jsonSerialize(),
            ),
        ]);
    }

    /**
     * @return iterable<ProjectionQueueItem>
     */
    public function observeProjectionQueue(string $handlerClass, float $maxExecutionTimeInSeconds = 0.0): iterable
    {
        $this->client->abortIn($maxExecutionTimeInSeconds);

        $observeEventsOptions = new ObserveEventsOptions(
            recursive: false,
            lowerBound: $this->resolveProjectionLowerBound($handlerClass),
        );

        foreach ($this->client->observeEvents($this->projectionQueueSubject($handlerClass), $observeEventsOptions) as $event) {
            if ($event->type !== self::TYPE_PROJECTION_ITEM) {
                continue;
            }

            $payload = is_array($event->data) ? $event->data : [];

            /** @var array<string, mixed> $payload */
            yield new ProjectionQueueItem(
                itemId: $event->id,
                handlerClass: $handlerClass,
                flowMessageReadonly: FlowMessageReadonly::createFromArray($payload),
            );
        }
    }

    public function ackProjectionQueueItem(string $handlerClass, string $itemId): void
    {
        $this->client->writeEvents([
            new EventCandidate(
                source: self::SOURCE,
                subject: $this->projectionCheckpointSubject($handlerClass),
                type: self::TYPE_PROJECTION_CHECKPOINT,
                data: [
                    'handlerClass' => $handlerClass,
                    'lastEventId' => $itemId,
                ],
            ),
        ]);
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

    private function projectionQueueSubject(string $handlerClass): string
    {
        return '/projection/' . md5($handlerClass) . '/queue';
    }

    private function projectionCheckpointSubject(string $handlerClass): string
    {
        return '/projection/' . md5($handlerClass) . '/checkpoint';
    }

    private function resolveProjectionLowerBound(string $handlerClass): ?Bound
    {
        $subject = $this->projectionCheckpointSubject($handlerClass);

        $checkpoints = $this->client->runEventQlQuery(
            'FROM e IN events ' .
            'WHERE e.subject == "' . $subject . '" ' .
            'AND e.type == "' . self::TYPE_PROJECTION_CHECKPOINT . '" ' .
            'ORDER BY e.id DESC ' .
            'TOP 1 ' .
            'PROJECT INTO e.data.lastEventId'
        );
        $lastEventId = iterator_to_array($checkpoints)[0] ?? null;

        return is_string($lastEventId)
            ? new Bound(id: $lastEventId, type: BoundType::EXCLUSIVE)
            : null;
    }
}
