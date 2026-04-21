<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use PDO as Client;
use PDOException;
use RuntimeException;
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
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;
use Wundii\Flowcrafter\ObserveItem;
use Wundii\Flowcrafter\ObserverException;
use Wundii\Flowcrafter\Schedule\ScheduleException;
use Wundii\Flowcrafter\Storage\Config\MySqlConfig;
use Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowSchemaEntity;
use Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;

class MySql extends Service implements StorageInterface
{
    public const TYPE_INSTANCE = 'flow_instance';

    public const TYPE_MESSAGE = 'flow_message';

    public const TYPE_EXCEPTION = 'flow_exception';

    public const TYPE_RESULT = 'flow_result';

    public const TYPE_QUEUE = 'flow_queue';

    public const TYPE_RUN = 'flow_run';

    public const TYPE_SCHEMA = 'flow_schema';

    public const TYPE_SOURCE_MESSAGE = 'flow_source_message';

    public const TYPE_SOURCE_STUB = 'flow_source_stub';

    protected Client $client;

    public function __construct(MySqlConfig $mySqlConfig, ?string $sqliteFile = null)
    {
        parent::__construct($sqliteFile);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $mySqlConfig->getHost(),
            $mySqlConfig->getPort(),
            $mySqlConfig->getDatabase(),
        );
        $this->client = new Client(
            $dsn,
            $mySqlConfig->getUsername(),
            $mySqlConfig->getPassword(),
            [
                Client::ATTR_ERRMODE => Client::ERRMODE_EXCEPTION,
                Client::ATTR_DEFAULT_FETCH_MODE => Client::FETCH_ASSOC,
                Client::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public function isPrimaryStorageInitialized(): bool
    {
        try {
            $stmt = $this->client->prepare(
                'SELECT COUNT(*) FROM information_schema.tables ' .
                'WHERE table_schema = DATABASE() AND table_name = :table_name'
            );
            $stmt->execute([
                ':table_name' => self::TYPE_INSTANCE,
            ]);

            return (int) $stmt->fetchColumn() === 1;
        } catch (Throwable) {
            return false;
        }
    }

    public function initializeDatabase(): void
    {
        parent::initializeDatabase();

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_schema (
                flow_schema_hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_schema_type VARCHAR(191) NOT NULL,
                flow_schema JSON NOT NULL,
                created_at DATETIME(3) NOT NULL,
                UNIQUE INDEX flow_schema_type_unique (flow_schema_type),
                INDEX flow_schema_hash (flow_schema_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_source_message (
                message_hash VARCHAR(32) NOT NULL PRIMARY KEY,
                message_source VARCHAR(255) NOT NULL,
                property_names JSON NOT NULL,
                time DATETIME(3) NOT NULL,
                INDEX flow_source_message_hash (message_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_source_stub (
                stub_hash VARCHAR(191) NOT NULL PRIMARY KEY,
                stub_source VARCHAR(191) NOT NULL,
                source_content TEXT NOT NULL,
                time DATETIME(3) NOT NULL,
                INDEX flow_source_stub_hash (stub_hash)
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
                `time` DATETIME(3) NOT NULL,
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
                flow_type VARCHAR(191) NOT NULL,
                queue_id VARCHAR(191) NULL,
                `time` DATETIME(3) NOT NULL,
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
                stub_hash VARCHAR(191) NOT NULL,
                message_type VARCHAR(64) NOT NULL,
                message_source VARCHAR(255) NOT NULL,
                message_hash VARCHAR(32) NOT NULL,
                message JSON NOT NULL,
                predecessor_hash VARCHAR(191) NULL,
                `time` DATETIME(3) NOT NULL,
                INDEX idx_flow_message_flow_hash (flow_hash),
                INDEX idx_flow_message_flow_runtime_hash (flow_runtime_hash),
                INDEX idx_flow_message_message_source (message_source),
                INDEX idx_flow_message_message_type (message_type),
                FOREIGN KEY (flow_hash) REFERENCES flow_instance(flow_hash),
                FOREIGN KEY (flow_runtime_hash) REFERENCES flow_run(flow_runtime_hash),
                FOREIGN KEY (stub_hash) REFERENCES flow_source_stub(stub_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_exception (
                hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_hash VARCHAR(191) NOT NULL,
                flow_runtime_hash VARCHAR(191) NOT NULL,
                flow_type VARCHAR(191) NOT NULL,
                stub_source VARCHAR(255) NOT NULL,
                stub_hash VARCHAR(191) NOT NULL,
                code INT(11) NOT NULL,
                message VARCHAR(2000) NOT NULL,
                file VARCHAR(2000) NOT NULL,
                line INT(11) NOT NULL,
                trace_string TEXT NOT NULL,
                `time` DATETIME(3) NOT NULL,
                INDEX idx_flow_exception_flow_hash (flow_hash),
                INDEX idx_flow_exception_flow_runtime_hash (flow_runtime_hash),
                INDEX idx_flow_exception_stub_source (stub_source),
                FOREIGN KEY (flow_hash) REFERENCES flow_instance(flow_hash),
                FOREIGN KEY (flow_runtime_hash) REFERENCES flow_run(flow_runtime_hash),
                FOREIGN KEY (stub_hash) REFERENCES flow_source_stub(stub_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_result (
                hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_hash VARCHAR(191) NOT NULL,
                flow_runtime_hash VARCHAR(191) NOT NULL,
                stub_source VARCHAR(255) NOT NULL,
                stub_hash VARCHAR(191) NULL,
                result TINYINT(1) NOT NULL,
                `time` DATETIME(3) NOT NULL,
                INDEX idx_flow_result_flow_hash (flow_hash),
                INDEX idx_flow_result_flow_runtime_hash (flow_runtime_hash),
                FOREIGN KEY (flow_hash) REFERENCES flow_instance(flow_hash),
                FOREIGN KEY (flow_runtime_hash) REFERENCES flow_run(flow_runtime_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS schedule_exception (
                hash VARCHAR(191) NOT NULL PRIMARY KEY,
                schedule_class VARCHAR(255) NOT NULL,
                schedule_name VARCHAR(255) NOT NULL,
                schedule_expression VARCHAR(100) NOT NULL,
                code INT(11) NOT NULL,
                message VARCHAR(2000) NOT NULL,
                file VARCHAR(2000) NOT NULL,
                line INT(11) NOT NULL,
                trace_string TEXT NOT NULL,
                `time` DATETIME(3) NOT NULL,
                INDEX idx_schedule_exception_time (time)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS observer_exception (
                hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_source VARCHAR(255) NOT NULL,
                message_source VARCHAR(255) NOT NULL,
                queue_id VARCHAR(191) NULL,
                code INT(11) NOT NULL,
                message VARCHAR(2000) NOT NULL,
                file VARCHAR(2000) NOT NULL,
                line INT(11) NOT NULL,
                trace_string TEXT NOT NULL,
                `time` DATETIME(3) NOT NULL,
                INDEX idx_observer_exception_time (time)
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
                flow_subject VARCHAR(255) NULL,
                message_source VARCHAR(255) NOT NULL,
                message JSON NOT NULL,
                include_stubs JSON NOT NULL,
                created_at DATETIME(3) NOT NULL,
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
            'SELECT * FROM flow_schema ' .
            'WHERE flow_schema_hash != :flow_schema_hash ' .
            'AND flow_schema_type = :flow_schema_type '
        );
        $stmt->execute([
            ':flow_schema_hash' => $flowSchema->getHash(),
            ':flow_schema_type' => $flowSchema->type(),
        ]);

        if ($stmt === false) {
            throw new RuntimeException('Could not prepare query.');
        }

        if ($stmt->fetch() !== false) {
            throw new InvalidArgumentException('The flow type "' . $flowSchema->type() . '" already exists.');
        }

        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_schema (flow_schema_hash, flow_schema_type, flow_schema, created_at) ' .
            'VALUES (:hash, :type, :flow_schema, :created_at)'
        );
        $stmt->execute([
            ':hash' => $flowSchema->getHash(),
            ':type' => $flowSchema->type(),
            ':flow_schema' => $flowSchemaJson,
            ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function registerMessageSource(MessageSourceEntity $messageSourceEntity): void
    {
        $propertyNamesJson = json_encode($messageSourceEntity->propertyNames);
        if (!is_string($propertyNamesJson)) {
            throw new RuntimeException('Could not serialize message source property names.');
        }

        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_source_message (message_hash, message_source, property_names, time) ' .
            'VALUES (:message_hash, :message_source, :property_names, :time)'
        );
        $stmt->execute([
            ':message_hash' => $messageSourceEntity->messageHash,
            ':message_source' => $messageSourceEntity->messageSource,
            ':property_names' => $propertyNamesJson,
            ':time' => $messageSourceEntity->time->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function registerStubSource(StubSourceEntity $stubSourceEntity): void
    {
        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_source_stub (stub_hash, stub_source, source_content, time) ' .
            'VALUES (:stub_hash, :stub_source, :source_content, :time)'
        );
        $stmt->execute([
            ':stub_hash' => $stubSourceEntity->stubHash,
            ':stub_source' => $stubSourceEntity->stubSource,
            ':source_content' => $stubSourceEntity->sourceContent,
            ':time' => $stubSourceEntity->time->format('Y-m-d H:i:s.v'),
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
            ':time' => $flow->getTime()->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function appendFlowRun(Flow $flow, ?string $queueId = null): void
    {
        $stmt = $this->client->prepare(
            'INSERT INTO flow_run (flow_runtime_hash, flow_hash, flow_type, queue_id, time) ' .
            'VALUES (:flow_runtime_hash, :flow_hash, :flow_type, :queue_id, :time)'
        );

        $stmt->execute([
            ':flow_runtime_hash' => $flow->getRuntimeHash(),
            ':flow_hash' => $flow->getHash(),
            ':flow_type' => $flow->getType(),
            ':queue_id' => $queueId,
            ':time' => $flow->getTime()->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function appendFlowMessage(FlowMessage $flowMessage): void
    {
        $messageJson = json_encode($flowMessage->getMessage());
        if (!is_string($messageJson)) {
            throw new RuntimeException('Could not serialize flow message.');
        }

        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_message (hash, flow_hash, flow_runtime_hash, stub_source, stub_hash, message_hash, message_type, message_source, predecessor_hash, time, message) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :stub_source, :stub_hash, :message_hash, :message_type, :message_source, :predecessor_hash, :time, :message)'
        );

        $stmt->execute([
            ':hash' => $flowMessage->getHash(),
            ':flow_hash' => $flowMessage->getFlowHash(),
            ':flow_runtime_hash' => $flowMessage->getFlowRuntimeHash(),
            ':stub_source' => $flowMessage->getStubSource(),
            ':stub_hash' => $flowMessage->getStubHash(),
            ':message_type' => $flowMessage->getMessageType()->value,
            ':message_source' => $flowMessage->getMessageSource(),
            ':message_hash' => $flowMessage->getMessageHash(),
            ':predecessor_hash' => $flowMessage->getPredecessorHash(),
            ':time' => $flowMessage->getTime()->format('Y-m-d H:i:s.v'),
            ':message' => $messageJson,
        ]);
    }

    public function appendFlowException(FlowException $flowException): void
    {
        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_exception (hash, flow_hash, flow_runtime_hash, flow_type, stub_source, stub_hash, code, message, file, line, trace_string, time) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :flow_type, :stub_source, :stub_hash, :code, :message, :file, :line, :trace_string, :time)'
        );

        $stmt->execute([
            ':hash' => $flowException->getHash(),
            ':flow_hash' => $flowException->getFlowHash(),
            ':flow_runtime_hash' => $flowException->getFlowRuntimeHash(),
            ':flow_type' => $flowException->getFlowType(),
            ':stub_source' => $flowException->getStubSource(),
            ':stub_hash' => $flowException->getStubHash(),
            ':code' => $flowException->getCode(),
            ':message' => $flowException->getMessage(),
            ':file' => $flowException->getFile(),
            ':line' => $flowException->getLine(),
            ':trace_string' => $flowException->getTraceString(),
            ':time' => $flowException->getTime()->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function appendScheduleException(ScheduleException $scheduleException): void
    {
        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO schedule_exception (hash, schedule_class, schedule_name, schedule_expression, code, message, file, line, trace_string, time) ' .
            'VALUES (:hash, :schedule_class, :schedule_name, :schedule_expression, :code, :message, :file, :line, :trace_string, :time)'
        );

        $stmt->execute([
            ':hash' => $scheduleException->getHash(),
            ':schedule_class' => $scheduleException->getScheduleClass(),
            ':schedule_name' => $scheduleException->getScheduleName(),
            ':schedule_expression' => $scheduleException->getScheduleExpression(),
            ':code' => $scheduleException->getCode(),
            ':message' => $scheduleException->getMessage(),
            ':file' => $scheduleException->getFile(),
            ':line' => $scheduleException->getLine(),
            ':trace_string' => $scheduleException->getTraceString(),
            ':time' => $scheduleException->getTime()->format('Y-m-d H:i:s.v'),
        ]);

        parent::appendScheduleException($scheduleException);
    }

    public function appendObserverException(ObserverException $observerException): void
    {
        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO observer_exception (hash, flow_source, message_source, queue_id, code, message, file, line, trace_string, time) ' .
            'VALUES (:hash, :flow_source, :message_source, :queue_id, :code, :message, :file, :line, :trace_string, :time)'
        );

        $stmt->execute([
            ':hash' => $observerException->getHash(),
            ':flow_source' => $observerException->getFlowSource(),
            ':message_source' => $observerException->getMessageSource(),
            ':queue_id' => $observerException->getQueueId(),
            ':code' => $observerException->getCode(),
            ':message' => $observerException->getMessage(),
            ':file' => $observerException->getFile(),
            ':line' => $observerException->getLine(),
            ':trace_string' => $observerException->getTraceString(),
            ':time' => $observerException->getTime()->format('Y-m-d H:i:s.v'),
        ]);

        parent::appendObserverException($observerException);
    }

    public function appendFlowResult(FlowResult $flowResult): void
    {
        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_result (hash, flow_hash, flow_runtime_hash, stub_source, stub_hash, result, time) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :stub_source, :stub_hash, :result, :time)'
        );

        $stmt->execute([
            ':hash' => $flowResult->getHash(),
            ':flow_hash' => $flowResult->getFlowHash(),
            ':flow_runtime_hash' => $flowResult->getFlowRuntimeHash(),
            ':stub_source' => $flowResult->getStubSource(),
            ':stub_hash' => $flowResult->getStubHash(),
            ':result' => $flowResult->getResult() ? 1 : 0,
            ':time' => $flowResult->getTime()->format('Y-m-d H:i:s.v'),
        ]);
    }

    /**
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     */
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, ?array $message, array $includeStubs = [], ?string $flowSubject = null): void
    {
        Assert::classString($flowSource, FlowInterface::class);
        Assert::classString($messageSource, MessageInterface::class);

        $messageJson = json_encode($message);
        if (!is_string($messageJson)) {
            throw new RuntimeException('Could not serialize observe message payload.');
        }

        $includeStubsJson = json_encode($includeStubs);
        if (!is_string($includeStubsJson)) {
            throw new RuntimeException('Could not serialize includeStubs payload.');
        }

        $stmt = $this->client->prepare(
            'INSERT INTO flow_queue (type, flow_source, flow_hash, flow_subject, message_source, message, include_stubs, created_at)' .
            ' VALUES (:type, :flow_source, :flow_hash, :flow_subject, :message_source, :message, :include_stubs, :created_at)'
        );

        $stmt->execute([
            ':type' => $type,
            ':flow_source' => $flowSource,
            ':flow_hash' => $flowHash,
            ':flow_subject' => $flowSubject,
            ':message_source' => $messageSource,
            ':message' => $messageJson,
            ':include_stubs' => $includeStubsJson,
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
     * @return iterable<string>
     */
    public function findAllFlowHashes(): iterable
    {
        $stmt = $this->client->query('SELECT flow_hash FROM flow_instance ORDER BY time ASC');
        if ($stmt === false) {
            return;
        }

        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $hash) {
            /** @var array{flow_hash: string} $hash */
            yield $hash['flow_hash'];
        }
    }

    /**
     * @return iterable<FlowSchemaEntity>
     */
    public function findAllSchemas(): iterable
    {
        $stmt = $this->client->query(
            'SELECT * FROM flow_schema'
        );

        if ($stmt === false) {
            return;
        }

        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $row) {
            /** @var array{flow_schema: string, flow_schema_hash: string, flow_schema_type: string} $row */
            $flowSchema = $row['flow_schema'];
            $flowSchemaArray = json_decode($flowSchema, true);
            if (!is_array($flowSchemaArray)) {
                throw new RuntimeException('Could not validate flow schema payload.');
            }

            $stubs = $flowSchemaArray['stubs'] ?? null;
            if (!is_array($stubs)) {
                throw new RuntimeException('Could not validate flow schema stubs.');
            }

            yield new FlowSchemaEntity(
                $row['flow_schema_hash'],
                $row['flow_schema_type'],
                $stubs,
            );
        }
    }

    /**
     * @return iterable<MessageSourceEntity>
     */
    public function findAllMessageSources(): iterable
    {
        $stmt = $this->client->query(
            'SELECT * FROM flow_source_message ORDER BY time ASC'
        );

        if ($stmt === false) {
            return;
        }

        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $row) {
            /** @var array{message_hash: string, message_source: string, property_names: string, time: string} $row */
            /** @var array<string, list<string>> $propertyNames */
            $propertyNames = json_decode($row['property_names'], true) ?? [];
            /** @var class-string<MessageInterface> $messageSourceClass */
            $messageSourceClass = $row['message_source'];
            yield new MessageSourceEntity(
                messageHash: $row['message_hash'],
                messageSource: $messageSourceClass,
                propertyNames: $propertyNames,
                time: new DateTimeImmutable($row['time']),
            );
        }
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
            /** @var array{queue_id: string, type: string, flow_subject: string|null, flow_source: class-string<\Wundii\Flowcrafter\Interface\FlowInterface>, flow_hash: string|null, message_source: string, message: string, include_stubs: string|null} $row */
            /** @var class-string[] $includeStubsParsed */
            $includeStubsParsed = json_decode($row['include_stubs'] ?? '[]', true) ?? [];

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
                includeStubs: $includeStubsParsed,
            );
        }
    }

    public function findFlowInstanceByHash(string $flowHash): ?FlowInstanceEntity
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

        $flowHash = is_string($instance['flow_hash'] ?? null) ? $instance['flow_hash'] : '';
        $flowType = is_string($instance['flow_type'] ?? null) ? $instance['flow_type'] : '';
        /** @var class-string<\Wundii\Flowcrafter\Interface\FlowInterface> $flowSource */
        $flowSource = is_string($instance['flow_source'] ?? null) ? $instance['flow_source'] : '';
        $flowSubject = is_string($instance['flow_subject'] ?? null) ? $instance['flow_subject'] : null;
        $flowSchemaHash = is_string($instance['flow_schema_hash'] ?? null) ? $instance['flow_schema_hash'] : '';
        $time = is_string($instance['time'] ?? null) ? $instance['time'] : 'now';

        return new FlowInstanceEntity(
            flowHash: $flowHash,
            flowType: $flowType,
            flowSource: $flowSource,
            flowSubject: $flowSubject,
            flowSchemaHash: $flowSchemaHash,
            time: new DateTimeImmutable($time),
        );
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
            'SELECT * FROM flow_schema ' .
            'WHERE flow_schema_hash = :flow_schema_hash'
        );
        $stmt->execute([
            ':flow_schema_hash' => $flowArray['flowSchemaHash'],
        ]);

        $row = $stmt->fetch();
        $flowSchema = is_array($row) ? ($row['flow_schema'] ?? null) : null;
        if (!is_string($flowSchema) || !json_validate($flowSchema)) {
            throw new RuntimeException('Invalid flow schema from Database');
        }

        $flowArray['flowSchema'] = json_decode($flowSchema, true);
        if (!is_array($flowArray['flowSchema'])) {
            throw new RuntimeException('Invalid flow schema');
        }

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_message ' .
            'WHERE flow_hash = :flow_hash ' .
            'ORDER BY time ASC'
        );
        $stmt->execute([
            ':flow_hash' => $flowHash,
        ]);

        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $message) {
            /** @var array{hash: string, flow_hash: string, flow_runtime_hash: string, stub_source: string, stub_hash: string|null, message_type: string, message_source: string, message_hash: string, message: string, predecessor_hash: string, time: string} $message */
            $messageJson = $message['message'];
            if (!json_validate($messageJson)) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            $messageArray = json_decode($messageJson, true);
            if (!is_array($messageArray) && $messageArray !== null) {
                throw new RuntimeException('Could not validate flow message payload.');
            }

            $flowArray['flowMessages'][] = [
                'hash' => $message['hash'],
                'flowHash' => $message['flow_hash'],
                'flowRuntimeHash' => $message['flow_runtime_hash'],
                'stubSource' => $message['stub_source'],
                'stubHash' => $message['stub_hash'],
                'messageType' => $message['message_type'],
                'messageSource' => $message['message_source'],
                'messageHash' => $message['message_hash'],
                'message' => $messageArray,
                'predecessorHash' => $message['predecessor_hash'],
                'time' => $message['time'],
            ];
        }

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_exception ' .
            'WHERE flow_hash = :flow_hash ' .
            'ORDER BY time ASC'
        );
        $stmt->execute([
            ':flow_hash' => $flowHash,
        ]);

        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $exception) {
            /** @var array{hash: string, flow_hash: string, flow_runtime_hash: string, flow_type: string, stub_source: string, stub_hash: string|null, code: int|string, message: string, file: string, line: int|string, trace_string: string, time: string} $exception */
            $flowArray['flowExceptions'][] = [
                'hash' => $exception['hash'],
                'flowHash' => $exception['flow_hash'],
                'flowRuntimeHash' => $exception['flow_runtime_hash'],
                'flowType' => $exception['flow_type'],
                'stubSource' => $exception['stub_source'],
                'stubHash' => $exception['stub_hash'],
                'code' => $exception['code'],
                'message' => $exception['message'],
                'file' => $exception['file'],
                'line' => $exception['line'],
                'traceString' => $exception['trace_string'],
                'time' => $exception['time'],
            ];
        }

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_result ' .
            'WHERE flow_hash = :flow_hash ' .
            'ORDER BY time ASC'
        );
        $stmt->execute([
            ':flow_hash' => $flowHash,
        ]);

        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $result) {
            /** @var array{hash: string, flow_hash: string, flow_runtime_hash: string, stub_source: string, stub_hash: string|null, result: int|string, time: string} $result */
            $flowArray['flowResults'][] = [
                'hash' => $result['hash'],
                'flowHash' => $result['flow_hash'],
                'flowRuntimeHash' => $result['flow_runtime_hash'],
                'stubSource' => $result['stub_source'],
                'stubHash' => $result['stub_hash'],
                'result' => (bool) $result['result'],
                'time' => $result['time'],
            ];
        }

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_run ' .
            'WHERE flow_hash = :flow_hash ' .
            'ORDER BY `time` ASC'
        );
        $stmt->execute([
            ':flow_hash' => $flowHash,
        ]);

        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $run) {
            /** @var array{flow_hash: string, flow_runtime_hash: string, flow_type: string, time: string, queue_id: string} $run */
            $flowArray['flowRuns'][] = [
                'flowHash' => $run['flow_hash'],
                'flowRuntimeHash' => $run['flow_runtime_hash'],
                'flowType' => $run['flow_type'],
                'time' => $run['time'],
                'queueId' => $run['queue_id'],
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

        /** @var array<string, mixed> $run */
        $flowHash = isset($run['flow_hash']) && is_string($run['flow_hash']) ? $run['flow_hash'] : '';
        if ($flowHash === '') {
            return null;
        }

        return $this->findFlowByHash($flowHash);
    }

    /**
     * @throws Exception
     */
    public function findStubSourceByHash(string $stubHash): ?StubSourceEntity
    {
        $stmt = $this->client->prepare(
            'SELECT * FROM flow_source_stub ' .
            'WHERE stub_hash = :stub_hash'
        );
        $stmt->execute([
            ':stub_hash' => $stubHash,
        ]);

        $stubSource = $stmt->fetch();
        if ($stubSource === false) {
            return null;
        }

        /** @var array{stub_hash: string, stub_source: class-string<StubInterface>, source_content: string, time: string} $stubSource */
        return new StubSourceEntity(
            stubHash: $stubSource['stub_hash'],
            stubSource: $stubSource['stub_source'],
            sourceContent: $stubSource['source_content'],
            time: new DateTimeImmutable($stubSource['time']),
        );
    }

    /**
     * @param class-string $stubSource
     * @return iterable<StubSourceEntity>
     */
    public function findStubSourcesByStubSource(string $stubSource): iterable
    {
        $stmt = $this->client->prepare(
            'SELECT * FROM flow_source_stub ' .
            'WHERE stub_source = :stub_source ' .
            'ORDER BY `time` ASC'
        );
        $stmt->execute([
            ':stub_source' => $stubSource,
        ]);

        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $stubSource) {
            /** @var array{stub_hash: string, stub_source: class-string<StubInterface>, source_content: string, time: string} $stubSource */
            yield new StubSourceEntity(
                stubHash: $stubSource['stub_hash'],
                stubSource: $stubSource['stub_source'],
                sourceContent: $stubSource['source_content'],
                time: new DateTimeImmutable($stubSource['time']),
            );
        }
    }

    /**
     * @param class-string $messageSource
     * @return iterable<MessageSourceEntity>
     */
    public function findMessageSourceByMessageSource(string $messageSource): iterable
    {
        $stmt = $this->client->prepare(
            'SELECT message_hash, message_source, property_names, time FROM flow_source_message ' .
            'WHERE message_source = :message_source ORDER BY time ASC'
        );
        $stmt->execute([
            ':message_source' => $messageSource,
        ]);
        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $row) {
            /** @var array{message_hash: string, message_source: string, property_names: string, time: string} $row */
            /** @var array<string, list<string>> $propertyNames */
            $propertyNames = json_decode($row['property_names'], true) ?? [];
            /** @var class-string<MessageInterface> $messageSourceClass */
            $messageSourceClass = $row['message_source'];
            yield new MessageSourceEntity(
                messageHash: $row['message_hash'],
                messageSource: $messageSourceClass,
                propertyNames: $propertyNames,
                time: new DateTimeImmutable($row['time']),
            );
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

            $includeStubsRaw = $row['include_stubs'] ?? '[]';
            /** @var class-string[] $includeStubsParsed */
            $includeStubsParsed = is_string($includeStubsRaw) ? (json_decode($includeStubsRaw, true) ?? []) : [];

            /** @var array{queue_id: string, type: string, flow_source: class-string<FlowInterface>, flow_hash: ?string, flow_subject: ?string, message_source: string, message: string, include_stubs?: string} $row */
            return new ObserveItem(
                queueId: (string) $row['queue_id'],
                type: $row['type'],
                flowSubject: $row['flow_subject'] ?? null,
                flowSource: $row['flow_source'],
                flowHash: $row['flow_hash'],
                messageSource: $row['message_source'],
                message: $message,
                includeStubs: $includeStubsParsed,
            );
        } catch (PDOException $pdoException) {
            if ($this->client->inTransaction()) {
                $this->client->rollBack();
            }

            throw new RuntimeException('Could not fetch observe queue item.', 0, $pdoException);
        }
    }
}
