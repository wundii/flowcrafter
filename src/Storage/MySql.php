<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use DateTimeImmutable;
use Exception;
use PDO as Client;
use PDOException;
use RuntimeException;
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

class MySql implements StorageInterface
{
    public const TYPE_INSTANCE = 'flow_instance';

    public const TYPE_MESSAGE = 'flow_message';

    public const TYPE_EXCEPTION = 'flow_exception';

    public const TYPE_QUEUE = 'flow_queue';

    public const TYPE_RUN = 'flow_run';

    public const TYPE_SCHEMA = 'flow_schema';

    protected Client $client;

    public function __construct(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password = '',
    ) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
        $this->client = new Client($dsn, $username, $password, [
            Client::ATTR_ERRMODE => Client::ERRMODE_EXCEPTION,
            Client::ATTR_DEFAULT_FETCH_MODE => Client::FETCH_ASSOC,
            Client::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function initializeDatabase(): void
    {
        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_schema (
                flow_schema_hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_schema JSON NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX flow_schema_hash (flow_schema_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_instance (
                flow_hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_type VARCHAR(191) NOT NULL,
                flow_source VARCHAR(255) NOT NULL,
                flow_subject VARCHAR(255) NULL,
                flow_schema_hash VARCHAR(191) NOT NULL,
                `time` DATETIME NOT NULL,
                INDEX idx_flow_instance_flow_hash (flow_hash),
                INDEX idx_flow_instance_flow_schema_hash (flow_schema_hash),
                INDEX idx_flow_instance_flow_subject (flow_subject),
                INDEX idx_flow_instance_flow_type (flow_type),
                FOREIGN KEY (flow_schema_hash) REFERENCES flow_schema(flow_schema_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_run (
                flow_runtime_hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_hash VARCHAR(191) NOT NULL,
                queue_id VARCHAR(191) NULL,
                `time` DATETIME NOT NULL,
                INDEX idx_flow_run_flow_hash (flow_hash),
                INDEX idx_flow_run_runtime_hash (flow_runtime_hash),
                FOREIGN KEY (flow_hash) REFERENCES flow_instance(flow_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_message (
                hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_hash VARCHAR(191) NOT NULL,
                flow_runtime_hash VARCHAR(191) NOT NULL,
                stub_source VARCHAR(255) NOT NULL,
                message_type VARCHAR(64) NOT NULL,
                message_source VARCHAR(255) NOT NULL,
                message JSON NOT NULL,
                predecessor_hash VARCHAR(191) NULL,
                `time` DATETIME NOT NULL,
                INDEX idx_flow_message_flow_hash (flow_hash),
                INDEX idx_flow_message_flow_runtime_hash (flow_runtime_hash),
                INDEX idx_flow_message_message_source (message_source),
                INDEX idx_flow_message_message_type (message_type),
                FOREIGN KEY (flow_hash) REFERENCES flow_instance(flow_hash),
                FOREIGN KEY (flow_runtime_hash) REFERENCES flow_run(flow_runtime_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_exception (
                hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_hash VARCHAR(191) NOT NULL,
                flow_runtime_hash VARCHAR(191) NOT NULL,
                stub_source VARCHAR(255) NOT NULL,
                code INT(11) NOT NULL,
                message VARCHAR(2000) NOT NULL,
                file VARCHAR(2000) NOT NULL,
                line INT(11) NOT NULL,
                traceString TEXT NOT NULL,
                `time` DATETIME NOT NULL,
                INDEX idx_flow_exception_flow_hash (flow_hash),
                INDEX idx_flow_exception_flow_runtime_hash (flow_runtime_hash),
                INDEX idx_flow_exception_stub_source (stub_source),
                FOREIGN KEY (flow_hash) REFERENCES flow_instance(flow_hash),
                FOREIGN KEY (flow_runtime_hash) REFERENCES flow_run(flow_runtime_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_queue (
                queue_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `type` VARCHAR(191) NOT NULL,
                flow_source VARCHAR(255) NOT NULL,
                flow_hash VARCHAR(191) NULL,
                message_source VARCHAR(255) NOT NULL,
                message JSON NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_flow_queue_created_at (created_at)
            )
            SQL
        );
    }

    public function registerFlowSchema(FlowSchema $flowSchema): void
    {
        $flowSchemaJson = json_encode($flowSchema);
        if (!is_string($flowSchemaJson)) {
            throw new RuntimeException('Could not serialize flow schema.');
        }

        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_schema (flow_schema_hash, flow_schema, created_at) ' .
            'VALUES (:hash, CAST(:flow_schema AS JSON), :created_at)'
        );
        $stmt->execute([
            ':hash' => $flowSchema->getHash(),
            ':flow_schema' => $flowSchemaJson,
            ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function registerFlowInstance(Flow $flow): void
    {
        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_instance (flow_hash, flow_type, flow_source, flow_subject, flow_schema_hash, time) ' .
            'VALUES (:flow_hash, :flow_type, :flow_source, :flow_subject, :flow_schema_hash, :time)'
        );

        $stmt->execute([
            ':flow_hash' => $flow->getHash(),
            ':flow_type' => $flow->getType(),
            ':flow_source' => $flow->getSource(),
            ':flow_subject' => $flow->getSubject(),
            ':flow_schema_hash' => $flow->getSchema()->getHash(),
            ':time' => $flow->getTime()->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function appendFlowRun(Flow $flow, ?string $queueId = null): void
    {
        $stmt = $this->client->prepare(
            'INSERT INTO flow_run (flow_runtime_hash, flow_hash, queue_id, time) ' .
            'VALUES (:flow_runtime_hash, :flow_hash, :queue_id, :time)'
        );

        $stmt->execute([
            ':flow_runtime_hash' => $flow->getRuntimeHash(),
            ':flow_hash' => $flow->getHash(),
            ':queue_id' => $queueId,
            ':time' => $flow->getTime()->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function appendFlowMessage(FlowMessage $flowMessage): void
    {
        $messageJson = json_encode($flowMessage->getMessage());
        if (!is_string($messageJson)) {
            throw new RuntimeException('Could not serialize flow message.');
        }

        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_message (hash, flow_hash, flow_runtime_hash, stub_source, message_type, message_source, predecessor_hash, time, message) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :stub_source, :message_type, :message_source, :predecessor_hash, :time, CAST(:message AS JSON))'
        );

        $stmt->execute([
            ':hash' => $flowMessage->getHash(),
            ':flow_hash' => $flowMessage->getFlowHash(),
            ':flow_runtime_hash' => $flowMessage->getFlowRuntimeHash(),
            ':stub_source' => $flowMessage->getStubSource(),
            ':message_type' => $flowMessage->getMessageType()->value,
            ':message_source' => $flowMessage->getMessageSource(),
            ':predecessor_hash' => $flowMessage->getPredecessorHash(),
            ':time' => $flowMessage->getTime()->format('Y-m-d H:i:s.u'),
            ':message' => $messageJson,
        ]);
    }

    public function appendFlowException(FlowException $flowException): void
    {
        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_exception (hash, flow_hash, flow_runtime_hash, stub_source, code, message, file, line, traceString, time) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :stub_source, :code, :message, :file, :line, :traceString, :time)'
        );

        $stmt->execute([
            ':hash' => $flowException->getHash(),
            ':flow_hash' => $flowException->getFlowHash(),
            ':flow_runtime_hash' => $flowException->getFlowRuntimeHash(),
            ':stub_source' => $flowException->getStubSource(),
            ':code' => $flowException->getCode(),
            ':message' => $flowException->getMessage(),
            ':file' => $flowException->getFile(),
            ':line' => $flowException->getLine(),
            ':traceString' => $flowException->getTraceString(),
            ':time' => $flowException->getTime()->format('Y-m-d H:i:s.u'),
        ]);
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

        $messageJson = json_encode($message);
        if (!is_string($messageJson)) {
            throw new RuntimeException('Could not serialize observe message payload.');
        }

        $stmt = $this->client->prepare(
            'INSERT INTO flow_queue (type, flow_source, flow_hash, message_source, message, created_at)' .
            ' VALUES (:type, :flow_source, :flow_hash, :message_source, CAST(:message AS JSON), :created_at)'
        );

        $stmt->execute([
            ':type' => $type,
            ':flow_source' => $flowSource,
            ':flow_hash' => $flowHash,
            ':message_source' => $messageSource,
            ':message' => $messageJson,
            ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u'),
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

    /**
     * @return FlowEntity[]
     * @throws Exception
     */
    public function findAllFlows(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000): iterable
    {
        $top = max(1, $top);

        $stmt = $this->client->query(
            'SELECT * FROM flow_instance' .
            ' ORDER BY flow_hash ' . $sortEnum->name .
            ' LIMIT ' . (int) $top
        );

        if ($stmt === false) {
            return [];
        }

        foreach ($stmt->fetchAll() as $row) {
            yield new FlowEntity(
                flowHash: $row['flow_hash'] ?? '',
                flowType: $row['flow_type'] ?? '',
                flowSource: $row['flow_source'] ?? '',
                flowSubject: $row['flow_subject'] ?? null,
                time: new DateTimeImmutable($row['time'] ?? 'now'),
            );
        }
    }

    /**
     * @return FlowEntity[]
     * @throws Exception
     */
    public function findFlowsBySource(string $flowSource, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000): iterable
    {
        if ($flowSource === '' || $flowSource === '*') {
            yield from $this->findAllFlows($sortEnum, $top);
            return;
        }

        $top = max(1, $top);

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_instance' .
            ' WHERE flow_source = :flow_source ORDER BY flow_hash ' . $sortEnum->name . ' LIMIT :top'
        );
        $stmt->bindValue(':flow_source', $flowSource);
        $stmt->bindValue(':top', $top, Client::PARAM_INT);
        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            yield new FlowEntity(
                flowHash: $row['flow_hash'] ?? '',
                flowType: $row['flow_type'] ?? '',
                flowSource: $row['flow_source'] ?? '',
                flowSubject: $row['flow_subject'] ?? null,
                time: new DateTimeImmutable($row['time'] ?? 'now'),
            );
        }
    }

    /**
     * @return FlowException[]
     * @throws Exception
     */
    public function findAllExceptions(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000): iterable
    {
        $top = max(1, $top);

        $stmt = $this->client->query(
            'SELECT * FROM flow_exception ' .
            'ORDER BY flow_hash ' . $sortEnum->name . ' ' .
            'LIMIT ' . (int) $top
        );

        if ($stmt === false) {
            return [];
        }

        foreach ($stmt->fetchAll() as $row) {
            yield new FlowException(
                flowHash: $row['flow_hash'] ?? '',
                flowRuntimeHash: $row['flow_runtime_hash'] ?? '',
                stubSource: $row['stub_source'] ?? '',
                code: $row['code'] ?? 0,
                message: $row['message'] ?? '',
                file: $row['file'] ?? '',
                line: $row['line'] ?? 0,
                traceString: $row['trace_string'] ?? '',
                time: new DateTimeImmutable($row['time'] ?? 'now'),
                hash: $row['hash'] ?? '',
            );
        }
    }

    /**
     * @return FlowException[]
     * @throws Exception
     */
    public function findExceptionsByFlowHash(string $flowHash, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000): iterable
    {
        if ($flowHash === '' || $flowHash === '*') {
            yield from $this->findAllExceptions($sortEnum, $top);
            return;
        }

        $top = max(1, $top);

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_exception ' .
            'WHERE flow_hash = :flow_hash ' .
            'ORDER BY flow_hash ' . $sortEnum->name . ' ' .
            'LIMIT :top'
        );
        $stmt->bindValue(':flow_hash', $flowHash);
        $stmt->bindValue(':top', $top, Client::PARAM_INT);
        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            yield new FlowException(
                flowHash: $row['flow_hash'] ?? '',
                flowRuntimeHash: $row['flow_runtime_hash'] ?? '',
                stubSource: $row['stub_source'] ?? '',
                code: $row['code'] ?? 0,
                message: $row['message'] ?? '',
                file: $row['file'] ?? '',
                line: $row['line'] ?? 0,
                traceString: $row['trace_string'] ?? '',
                time: new DateTimeImmutable($row['time'] ?? 'now'),
                hash: $row['hash'] ?? '',
            );
        }
    }

    public function findFlowByHash(string $flowHash): ?Flow
    {
        $stmt = $this->client->prepare(
            'SELECT * FROM flow_instance ' .
            'WHERE flow_hash = :flow_hash LIMIT 1'
        );
        $stmt->execute([
            ':flow_hash' => $flowHash,
        ]);

        $instance = $stmt->fetch();
        if (!is_array($instance)) {
            return null;
        }

        $flowArray = [
            'flowHash' => $instance['flow_hash'] ?? '',
            'flowSchemaHash' => $instance['flow_schema_hash'] ?? '',
            'flowSource' => $instance['flow_source'] ?? '',
            'flowSubject' => $instance['flow_subject'] ?? '',
            'flowType' => $instance['flow_type'] ?? '',
            'time' => $instance['time'] ?? 'now',
        ];

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_message ' .
            'WHERE flow_hash = :flow_hash'
        );
        $stmt->execute([
            ':flow_hash' => $flowHash,
        ]);

        foreach ($stmt->fetchAll() as $message) {
            $messageJson = $message['message'] ?? '';
            if (!json_validate($messageJson)) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            $messageArray = json_decode($messageJson, true);
            if (!is_array($messageArray)) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            $flowArray['flowMessages'][] = [
                'hash' => $message['hash'] ?? '',
                'flowHash' => $message['flow_hash'] ?? '',
                'flowRuntimeHash' => $message['flow_runtime_hash'] ?? '',
                'stubSource' => $message['stub_source'] ?? '',
                'messageType' => $message['message_type'] ?? '',
                'messageSource' => $message['message_source'] ?? '',
                'message' => $messageArray,
                'predecessor' => $message['predecessor'] ?? '',
                'time' => $message['time'] ?? 'now',
            ];
        }

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_exception ' .
            'WHERE flow_hash = :flow_hash'
        );
        $stmt->execute([
            ':flow_hash' => $flowHash,
        ]);

        foreach ($stmt->fetchAll() as $exception) {
            $flowArray['flowExceptions'][] = [
                'hash' => $exception['hash'] ?? '',
                'flowHash' => $exception['flow_hash'] ?? '',
                'flowRuntimeHash' => $exception['flow_runtime_hash'] ?? '',
                'stubSource' => $exception['stub_source'] ?? '',
                'code' => $exception['code'] ?? 0,
                'message' => $exception['message'] ?? '',
                'file' => $exception['file'] ?? '',
                'line' => $exception['line'] ?? 0,
                'traceString' => $exception['traceString'] ?? '',
                'time' => $exception['time'] ?? 'now',
            ];
        }

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_run ' .
            'WHERE flow_hash = :flow_hash'
        );
        $stmt->execute([
            ':flow_hash' => $flowHash,
        ]);

        foreach ($stmt->fetchAll() as $run) {
            $flowArray['flowRuns'][] = [
                'flowHash' => $run['flow_hash'] ?? '',
                'flowRuntimeHash' => $run['flow_runtime_hash'] ?? '',
                'time' => $run['time'] ?? 'now',
                'queueId' => $run['queueId'] ?? '',
            ];
        }

        return Converter::arrayToFlow($flowArray);
    }

    public function findFlowByRuntimeHash(string $flowRuntimeHash): ?Flow
    {
        $stmt = $this->client->prepare(
            'SELECT flow_hash FROM flow_run ' .
            'WHERE flow_runtime_hash = :flow_runtime_hash LIMIT 1'
        );
        $stmt->execute([
            ':flow_runtime_hash' => $flowRuntimeHash,
        ]);

        $run = $stmt->fetch();
        if ($run === false) {
            return null;
        }

        /** @phpstan-ignore-next-line */
        $flowHash = $run['flow_hash'] ?? '';
        if ($flowHash === '') {
            return null;
        }

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_instance ' .
            'WHERE flow_hash = :flow_hash LIMIT 1'
        );
        $stmt->execute([
            ':flow_hash' => $flowHash,
        ]);

        $instance = $stmt->fetch();
        if (!is_array($instance)) {
            return null;
        }

        $flowArray = [
            'flowHash' => $instance['flow_hash'] ?? '',
            'flowSchemaHash' => $instance['flow_schema_hash'] ?? '',
            'flowSource' => $instance['flow_source'] ?? '',
            'flowSubject' => $instance['flow_subject'] ?? '',
            'flowType' => $instance['flow_type'] ?? '',
            'time' => $instance['time'] ?? 'now',
        ];

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_message ' .
            'WHERE flow_runtime_hash = :flow_runtime_hash'
        );
        $stmt->execute([
            ':flow_runtime_hash' => $flowRuntimeHash,
        ]);

        foreach ($stmt->fetchAll() as $message) {
            $messageJson = $message['message'] ?? '';
            if (!json_validate($messageJson)) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            $messageArray = json_decode($messageJson, true);
            if (!is_array($messageArray)) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            $flowArray['flowMessages'][] = [
                'hash' => $message['hash'] ?? '',
                'flowHash' => $message['flow_hash'] ?? '',
                'flowRuntimeHash' => $message['flow_runtime_hash'] ?? '',
                'stubSource' => $message['stub_source'] ?? '',
                'messageType' => $message['message_type'] ?? '',
                'messageSource' => $message['message_source'] ?? '',
                'message' => $messageArray,
                'predecessor' => $message['predecessor'] ?? '',
                'time' => $message['time'] ?? 'now',
            ];
        }

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_exception ' .
            'WHERE flow_runtime_hash = :flow_runtime_hash'
        );
        $stmt->execute([
            ':flow_runtime_hash' => $flowRuntimeHash,
        ]);

        foreach ($stmt->fetchAll() as $exception) {
            $flowArray['flowExceptions'][] = [
                'hash' => $exception['hash'] ?? '',
                'flowHash' => $exception['flow_hash'] ?? '',
                'flowRuntimeHash' => $exception['flow_runtime_hash'] ?? '',
                'stubSource' => $exception['stub_source'] ?? '',
                'code' => $exception['code'] ?? 0,
                'message' => $exception['message'] ?? '',
                'file' => $exception['file'] ?? '',
                'line' => $exception['line'] ?? 0,
                'traceString' => $exception['traceString'] ?? '',
                'time' => $exception['time'] ?? 'now',
            ];
        }

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_run ' .
            'WHERE flow_runtime_hash = :flow_runtime_hash'
        );
        $stmt->execute([
            ':flow_runtime_hash' => $flowRuntimeHash,
        ]);

        foreach ($stmt->fetchAll() as $run) {
            $flowArray['flowRuns'][] = [
                'flowHash' => $run['flow_hash'] ?? '',
                'flowRuntimeHash' => $run['flow_runtime_hash'] ?? '',
                'time' => $run['time'] ?? 'now',
                'queueId' => $run['queueId'] ?? '',
            ];
        }

        return Converter::arrayToFlow($flowArray);
    }

    private function takeQueueItem(): ?ObserveItem
    {
        try {
            $this->client->beginTransaction();

            $stmt = $this->client->query(
                'SELECT * FROM flow_queue ' .
                'ORDER BY created_at ASC LIMIT 1'
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

            $this->client->commit();

            $messageJson = $row['message'] ?? '';
            if (!is_string($messageJson)) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            if (!json_validate($messageJson)) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            $messageArray = json_decode($messageJson, true);
            if (!is_array($messageArray)) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            return new ObserveItem(
                queueId: (string) $row['queue_id'],
                type: $row['type'] ?? '',
                flowSource: $row['flow_source'] ?? '',
                flowHash: $row['flow_hash'] ?? null,
                messageSource: $row['message_source'] ?? '',
                message: $messageArray,
            );
        } catch (PDOException $pdoException) {
            if ($this->client->inTransaction()) {
                $this->client->rollBack();
            }

            throw new RuntimeException('Could not fetch observe queue item.', 0, $pdoException);
        }
    }
}
