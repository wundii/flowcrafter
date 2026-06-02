<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Queue;

use DateTimeImmutable;
use Redis as Client;
use RuntimeException;
use Throwable;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowMessageReadonly;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Projection\ProjectionQueueItem;
use Wundii\Flowcrafter\Queue\Config\RedisQueueConfig;
use Wundii\Flowcrafter\Uuid;

final class RedisQueue implements QueueInterface
{
    public const QUEUE_KEY = 'flow:queue';

    public const PREFIX_PROJECTION_QUEUE = 'projection:queue:';

    private const PROJECTION_GROUP = 'projection_workers';

    private const PROJECTION_VISIBILITY_MS = 300_000;

    private readonly Client $client;

    public function __construct(RedisQueueConfig $redisQueueConfig)
    {
        $this->client = new Client();
        $this->client->connect($redisQueueConfig->getHost(), $redisQueueConfig->getPort());
        if ($redisQueueConfig->getPassword() !== null) {
            $this->client->auth($redisQueueConfig->getPassword());
        }
    }

    public function initializeQueue(): void
    {
        // No schema setup required: the observer queue is a Redis list and the
        // projection consumer groups are created lazily on first use.
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

        $data = [
            'type' => $type,
            'flowSource' => $flowSource,
            'flowHash' => $flowHash,
            'messageSource' => $messageSource,
            'message' => $message,
            'includeSteps' => $includeSteps,
            'flowSubject' => $flowSubject,
        ];

        $this->client->lPush(self::QUEUE_KEY, json_encode($data));
    }

    /**
     * @return iterable<ObserveItem>
     */
    public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable
    {
        $startExecutionTime = microtime(true);

        while (true) {
            if ($maxExecutionTimeInSeconds > 0.0 && (microtime(true) - $startExecutionTime) >= $maxExecutionTimeInSeconds) {
                break;
            }

            $result = $this->client->brPop(self::QUEUE_KEY, 1);
            if (!is_array($result)) {
                continue;
            }

            if (!array_key_exists(1, $result)) {
                continue;
            }

            $payload = json_decode($result[1], true);
            if (!is_array($payload)) {
                throw new RuntimeException('The flow message payload must be a valid JSON object.');
            }

            /** @var array{type: string, flowSource: class-string<FlowInterface>, flowHash: ?string, messageSource: string, message: array<mixed>, includeSteps?: class-string[], flowSubject?: ?string} $payload */
            yield new ObserveItem(
                queueId: Uuid::uuid7(new DateTimeImmutable())->toString(),
                type: $payload['type'],
                flowSubject: $payload['flowSubject'] ?? null,
                flowSource: $payload['flowSource'],
                flowHash: $payload['flowHash'],
                messageSource: $payload['messageSource'],
                message: $payload['message'],
                includeSteps: $payload['includeSteps'] ?? [],
            );
        }
    }

    public function openQueues(): int
    {
        $len = $this->client->lLen(self::QUEUE_KEY);
        return $len === false ? 0 : (int) $len;
    }

    /**
     * @return iterable<ObserveItem>
     */
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable
    {
        $items = $this->client->lRange(self::QUEUE_KEY, 0, -1);
        if ($items === false || $items === []) {
            return;
        }

        if ($sortEnum === SortEnum::ASC) {
            $items = array_reverse($items);
        }

        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }

            $payload = json_decode($item, true);
            if (!is_array($payload)) {
                continue;
            }

            /** @var array{queueId: string, type: string, flowSource: class-string<FlowInterface>, flowHash: ?string, messageSource: string, message: null|array<mixed>, includeSteps?: class-string[], flowSubject?: ?string} $payload */
            yield new ObserveItem(
                queueId: $payload['queueId'],
                type: $payload['type'],
                flowSubject: $payload['flowSubject'] ?? null,
                flowSource: $payload['flowSource'],
                flowHash: $payload['flowHash'],
                messageSource: $payload['messageSource'],
                message: $payload['message'],
                includeSteps: $payload['includeSteps'] ?? [],
            );
        }
    }

    public function appendProjectionQueueItem(FlowMessage $flowMessage, string $handlerClass): void
    {
        $payloadJson = json_encode($flowMessage);
        if (!is_string($payloadJson)) {
            throw new RuntimeException('Could not serialize projection queue payload.');
        }

        $streamKey = self::PREFIX_PROJECTION_QUEUE . $handlerClass;
        $this->ensureProjectionGroup($streamKey);
        $this->client->xAdd($streamKey, '*', [
            'payload' => $payloadJson,
        ]);
    }

    /**
     * @return iterable<ProjectionQueueItem>
     */
    public function observeProjectionQueue(string $handlerClass, float $maxExecutionTimeInSeconds = 0.0): iterable
    {
        $streamKey = self::PREFIX_PROJECTION_QUEUE . $handlerClass;
        $this->ensureProjectionGroup($streamKey);
        $consumer = $this->projectionConsumerName();
        $startExecutionTime = microtime(true);

        while (true) {
            if ($maxExecutionTimeInSeconds > 0.0 && (microtime(true) - $startExecutionTime) >= $maxExecutionTimeInSeconds) {
                break;
            }

            foreach ($this->fetchProjectionEntries($streamKey, $consumer) as $itemId => $payload) {
                yield new ProjectionQueueItem(
                    itemId: $itemId,
                    handlerClass: $handlerClass,
                    flowMessageReadonly: FlowMessageReadonly::createFromArray($payload),
                );
            }
        }
    }

    public function ackProjectionQueueItem(string $handlerClass, string $itemId): void
    {
        $streamKey = self::PREFIX_PROJECTION_QUEUE . $handlerClass;
        $this->client->xAck($streamKey, self::PROJECTION_GROUP, [$itemId]);
    }

    private function ensureProjectionGroup(string $streamKey): void
    {
        try {
            $this->client->xGroup('CREATE', $streamKey, self::PROJECTION_GROUP, '0', true);
        } catch (Throwable) {
            // BUSYGROUP: the consumer group already exists
        }
    }

    /**
     * Reclaim crashed-consumer entries first (crash recovery), then read new ones.
     *
     * @return array<string, array<string, mixed>> itemId => decoded FlowMessage payload
     */
    private function fetchProjectionEntries(string $streamKey, string $consumer): array
    {
        $claimed = $this->client->xAutoClaim($streamKey, self::PROJECTION_GROUP, $consumer, self::PROJECTION_VISIBILITY_MS, '0-0', 100);
        $entries = $this->normalizeStreamEntries(is_array($claimed) ? ($claimed[1] ?? []) : []);
        if ($entries !== []) {
            return $entries;
        }

        $result = $this->client->xReadGroup(self::PROJECTION_GROUP, $consumer, [
            $streamKey => '>',
        ], 100, 1000);
        if (!is_array($result) || !is_array($result[$streamKey] ?? null)) {
            return [];
        }

        return $this->normalizeStreamEntries($result[$streamKey]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function normalizeStreamEntries(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $entries = [];
        foreach ($raw as $itemId => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            $payloadJson = $fields['payload'] ?? null;
            if (!is_string($payloadJson)) {
                continue;
            }

            if (!json_validate($payloadJson)) {
                continue;
            }

            $payload = json_decode($payloadJson, true);
            if (!is_array($payload)) {
                continue;
            }

            /** @var array<string, mixed> $payload */
            $entries[(string) $itemId] = $payload;
        }

        return $entries;
    }

    private function projectionConsumerName(): string
    {
        $host = gethostname();

        return ($host !== false ? $host : 'worker') . ':' . getmypid();
    }
}
