<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Queue;

use DateTimeImmutable;
use PDO as Client;
use PDOException;
use RuntimeException;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowMessageReadonly;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\Projection\ProjectionQueueItem;
use Wundii\Flowcrafter\Queue\Config\MySqlQueueConfig;

final class MySqlQueue implements QueueInterface
{
    public const TABLE_QUEUE = 'flow_queue';

    public const TABLE_PROJECTION_QUEUE = 'projection_queue';

    private const PROJECTION_VISIBILITY_SECONDS = 300;

    private readonly Client $client;

    public function __construct(MySqlQueueConfig $mySqlQueueConfig)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $mySqlQueueConfig->getHost(),
            $mySqlQueueConfig->getPort(),
            $mySqlQueueConfig->getDatabase(),
        );
        $this->client = new Client(
            $dsn,
            $mySqlQueueConfig->getUsername(),
            $mySqlQueueConfig->getPassword(),
            [
                Client::ATTR_ERRMODE => Client::ERRMODE_EXCEPTION,
                Client::ATTR_DEFAULT_FETCH_MODE => Client::FETCH_ASSOC,
                Client::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public function initializeQueue(): void
    {
        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_queue (
                queue_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `type` VARCHAR(191) NOT NULL,
                flow_source VARCHAR(255) NOT NULL,
                flow_hash VARCHAR(191) NULL,
                flow_subject VARCHAR(255) NULL,
                message_source VARCHAR(255) NOT NULL,
                message JSON NOT NULL,
                include_steps JSON NOT NULL,
                created_at DATETIME(3) NOT NULL,
                INDEX idx_flow_queue_created_at (created_at)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS projection_queue (
                queue_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                handler_class VARCHAR(255) NOT NULL,
                flow_hash VARCHAR(191) NOT NULL,
                flow_type VARCHAR(191) NOT NULL,
                message_hash VARCHAR(191) NOT NULL,
                payload JSON NOT NULL,
                claimed_at DATETIME(3) NULL,
                created_at DATETIME(3) NOT NULL,
                INDEX idx_projection_queue_claim (handler_class, claimed_at, queue_id)
            )
            SQL
        );
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

        $messageJson = json_encode($message);
        if (!is_string($messageJson)) {
            throw new RuntimeException('Could not serialize observe message payload.');
        }

        $includeStepsJson = json_encode($includeSteps);
        if (!is_string($includeStepsJson)) {
            throw new RuntimeException('Could not serialize includeSteps payload.');
        }

        $stmt = $this->client->prepare(
            'INSERT INTO flow_queue (type, flow_source, flow_hash, flow_subject, message_source, message, include_steps, created_at)' .
            ' VALUES (:type, :flow_source, :flow_hash, :flow_subject, :message_source, :message, :include_steps, :created_at)'
        );

        $stmt->execute([
            ':type' => $type,
            ':flow_source' => $flowSource,
            ':flow_hash' => $flowHash,
            ':flow_subject' => $flowSubject,
            ':message_source' => $messageSource,
            ':message' => $messageJson,
            ':include_steps' => $includeStepsJson,
            ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
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

            $observeItem = $this->takeQueueItem();
            if ($observeItem instanceof ObserveItem) {
                yield $observeItem;
                continue;
            }

            usleep(200_000);
        }
    }

    public function openQueues(): int
    {
        $stmt = $this->client->query('SELECT COUNT(*) FROM flow_queue');
        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return iterable<ObserveItem>
     */
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable
    {
        $stmt = $this->client->query(
            'SELECT * FROM flow_queue ' .
            'ORDER BY queue_id ' . $sortEnum->name
        );

        if ($stmt === false) {
            return;
        }

        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $row) {
            /** @var array{queue_id: string, type: string, flow_subject: string|null, flow_source: class-string<FlowInterface>, flow_hash: string|null, message_source: string, message: string, include_steps: string|null} $row */
            /** @var class-string[] $includeStepsParsed */
            $includeStepsParsed = json_decode($row['include_steps'] ?? '[]', true) ?? [];

            $messageArray = json_decode($row['message'], true);
            if (!is_array($messageArray)) {
                throw new RuntimeException('Could not validate message payload.');
            }

            yield new ObserveItem(
                queueId: $row['queue_id'],
                type: $row['type'],
                flowSubject: $row['flow_subject'],
                flowSource: $row['flow_source'],
                flowHash: $row['flow_hash'],
                messageSource: $row['message_source'],
                message: $messageArray,
                includeSteps: $includeStepsParsed,
            );
        }
    }

    public function appendProjectionQueueItem(FlowMessage $flowMessage, string $handlerClass): void
    {
        $payloadJson = json_encode($flowMessage);
        if (!is_string($payloadJson)) {
            throw new RuntimeException('Could not serialize projection queue payload.');
        }

        $stmt = $this->client->prepare(
            'INSERT INTO projection_queue (handler_class, flow_hash, flow_type, message_hash, payload, created_at)' .
            ' VALUES (:handler_class, :flow_hash, :flow_type, :message_hash, :payload, :created_at)'
        );

        $stmt->execute([
            ':handler_class' => $handlerClass,
            ':flow_hash' => $flowMessage->getFlowHash(),
            ':flow_type' => $flowMessage->getFlowType(),
            ':message_hash' => $flowMessage->getHash(),
            ':payload' => $payloadJson,
            ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    /**
     * @return iterable<ProjectionQueueItem>
     */
    public function observeProjectionQueue(string $handlerClass, float $maxExecutionTimeInSeconds = 0.0): iterable
    {
        $startExecutionTime = microtime(true);

        while (true) {
            if ($maxExecutionTimeInSeconds > 0.0 && (microtime(true) - $startExecutionTime) >= $maxExecutionTimeInSeconds) {
                break;
            }

            $item = $this->claimProjectionQueueItem($handlerClass);
            if ($item instanceof ProjectionQueueItem) {
                yield $item;
                continue;
            }

            usleep(200_000);
        }
    }

    public function ackProjectionQueueItem(string $handlerClass, string $itemId): void
    {
        $stmt = $this->client->prepare(
            'DELETE FROM projection_queue WHERE queue_id = :queue_id AND handler_class = :handler_class'
        );
        $stmt->execute([
            ':queue_id' => $itemId,
            ':handler_class' => $handlerClass,
        ]);
    }

    private function claimProjectionQueueItem(string $handlerClass): ?ProjectionQueueItem
    {
        try {
            $this->client->beginTransaction();

            $visibility = (new DateTimeImmutable())
                ->modify('-' . self::PROJECTION_VISIBILITY_SECONDS . ' seconds')
                ->format('Y-m-d H:i:s.v');

            $stmt = $this->client->prepare(
                'SELECT * FROM projection_queue ' .
                'WHERE handler_class = :handler_class ' .
                'AND (claimed_at IS NULL OR claimed_at < :visibility) ' .
                'ORDER BY queue_id ASC LIMIT 1 ' .
                'FOR UPDATE SKIP LOCKED'
            );
            $stmt->execute([
                ':handler_class' => $handlerClass,
                ':visibility' => $visibility,
            ]);

            $row = $stmt->fetch(Client::FETCH_ASSOC);
            if (!is_array($row)) {
                $this->client->commit();
                return null;
            }

            $updateStmt = $this->client->prepare(
                'UPDATE projection_queue SET claimed_at = :now WHERE queue_id = :queue_id'
            );
            $updateStmt->execute([
                ':now' => (new DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                ':queue_id' => $row['queue_id'],
            ]);

            $this->client->commit();

            /** @var array{queue_id: string|int, handler_class: string, payload: string} $row */
            $payloadJson = $row['payload'];
            if (!json_validate($payloadJson)) {
                throw new RuntimeException('Could not validate projection queue payload.');
            }

            $payload = json_decode($payloadJson, true);
            if (!is_array($payload)) {
                throw new RuntimeException('Could not validate projection queue payload.');
            }

            /** @var array<string, mixed> $payload */
            return new ProjectionQueueItem(
                itemId: (string) $row['queue_id'],
                handlerClass: $handlerClass,
                flowMessageReadonly: FlowMessageReadonly::createFromArray($payload),
            );
        } catch (PDOException $pdoException) {
            if ($this->client->inTransaction()) {
                $this->client->rollBack();
            }

            throw new RuntimeException('Could not claim projection queue item.', 0, $pdoException);
        }
    }

    private function takeQueueItem(): ?ObserveItem
    {
        try {
            $this->client->beginTransaction();

            $stmt = $this->client->query(
                'SELECT * FROM flow_queue ' .
                'ORDER BY created_at ASC LIMIT 1 ' .
                'FOR UPDATE SKIP LOCKED'
            );

            $row = $stmt === false ? false : $stmt->fetch();
            if (!is_array($row)) {
                $this->client->commit();
                return null;
            }

            $deleteStmt = $this->client->prepare('DELETE FROM flow_queue WHERE queue_id = :queue_id');
            $deleteStmt->execute([
                ':queue_id' => $row['queue_id'],
            ]);

            if ($deleteStmt->rowCount() === 0) {
                $this->client->commit();
                return null;
            }

            $this->client->commit();

            $messageJson = $row['message'] ?? 'null';
            if (!is_string($messageJson)) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            if (!json_validate($messageJson)) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            $message = json_decode($messageJson, true);
            if (!is_array($message) && $message !== null) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            $includeStepsRaw = $row['include_steps'] ?? '[]';
            /** @var class-string[] $includeStepsParsed */
            $includeStepsParsed = is_string($includeStepsRaw) ? (json_decode($includeStepsRaw, true) ?? []) : [];

            /** @var array{queue_id: string, type: string, flow_source: class-string<FlowInterface>, flow_hash: ?string, flow_subject: ?string, message_source: string, message: string, include_steps?: string} $row */
            return new ObserveItem(
                queueId: (string) $row['queue_id'],
                type: $row['type'],
                flowSubject: $row['flow_subject'] ?? null,
                flowSource: $row['flow_source'],
                flowHash: $row['flow_hash'],
                messageSource: $row['message_source'],
                message: $message,
                includeSteps: $includeStepsParsed,
            );
        } catch (PDOException $pdoException) {
            if ($this->client->inTransaction()) {
                $this->client->rollBack();
            }

            throw new RuntimeException('Could not fetch observe queue item.', 0, $pdoException);
        }
    }
}
