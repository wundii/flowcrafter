<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use PDO as Client;
use PDOException;
use RuntimeException;
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
use Wundii\Flowcrafter\Storage\Config\MySqlConfig;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowStatsEntity;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;
use Wundii\Flowcrafter\Stub;

class MySql implements StorageInterface
{
    public const TYPE_INSTANCE = 'flow_instance';

    public const TYPE_MESSAGE = 'flow_message';

    public const TYPE_EXCEPTION = 'flow_exception';

    public const TYPE_RESULT = 'flow_result';

    public const TYPE_QUEUE = 'flow_queue';

    public const TYPE_RUN = 'flow_run';

    public const TYPE_SCHEMA = 'flow_schema';

    public const TYPE_SOURCE_STUB = 'flow_source_stub';

    protected Client $client;

    public function __construct(MySqlConfig $mySqlConfig)
    {
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

    public function initializeDatabase(): void
    {
        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_schema (
                flow_schema_hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_schema_type VARCHAR(191) NOT NULL,
                flow_schema JSON NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX flow_schema_type_unique (flow_schema_type),
                INDEX flow_schema_hash (flow_schema_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_source_stub (
                stub_hash VARCHAR(191) NOT NULL PRIMARY KEY,
                stub_source VARCHAR(191) NOT NULL,
                source_content TEXT NOT NULL,
                time DATETIME NOT NULL,
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
                flow_type VARCHAR(191) NOT NULL,
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
                stub_hash VARCHAR(191) NOT NULL,
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
                `time` DATETIME NOT NULL,
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
                `time` DATETIME NOT NULL,
                INDEX idx_flow_result_flow_hash (flow_hash),
                INDEX idx_flow_result_flow_runtime_hash (flow_runtime_hash),
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
                flow_subject VARCHAR(255) NULL,
                message_source VARCHAR(255) NOT NULL,
                message JSON NOT NULL,
                include_stubs JSON NOT NULL,
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
            ':created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @param class-string $stubSource
     */
    public function registerStubSource(string $stubSource): string
    {
        $stubSource = Stub::source($stubSource);

        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_source_stub (stub_hash, stub_source, source_content, time) ' .
            'VALUES (:stub_hash, :stub_source, :source_content, :time)'
        );
        $stmt->execute([
            ':stub_hash' => $stubSource->stubHash,
            ':stub_source' => $stubSource->stubSource,
            ':source_content' => $stubSource->sourceContent,
            ':time' => $stubSource->time->format('Y-m-d H:i:s.u'),
        ]);

        return $stubSource->stubHash;
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
            'INSERT INTO flow_run (flow_runtime_hash, flow_hash, flow_type, queue_id, time) ' .
            'VALUES (:flow_runtime_hash, :flow_hash, :flow_type, :queue_id, :time)'
        );

        $stmt->execute([
            ':flow_runtime_hash' => $flow->getRuntimeHash(),
            ':flow_hash' => $flow->getHash(),
            ':flow_type' => $flow->getType(),
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
            'INSERT IGNORE INTO flow_message (hash, flow_hash, flow_runtime_hash, stub_source, stub_hash, message_type, message_source, predecessor_hash, time, message) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :stub_source, :stub_hash, :message_type, :message_source, :predecessor_hash, :time, :message)'
        );

        $stmt->execute([
            ':hash' => $flowMessage->getHash(),
            ':flow_hash' => $flowMessage->getFlowHash(),
            ':flow_runtime_hash' => $flowMessage->getFlowRuntimeHash(),
            ':stub_source' => $flowMessage->getStubSource(),
            ':stub_hash' => $flowMessage->getStubHash(),
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
            'INSERT IGNORE INTO flow_exception (hash, flow_hash, flow_runtime_hash, stub_source, stub_hash, code, message, file, line, trace_string, time) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :stub_source, :stub_hash, :code, :message, :file, :line, :trace_string, :time)'
        );

        $stmt->execute([
            ':hash' => $flowException->getHash(),
            ':flow_hash' => $flowException->getFlowHash(),
            ':flow_runtime_hash' => $flowException->getFlowRuntimeHash(),
            ':stub_source' => $flowException->getStubSource(),
            ':stub_hash' => $flowException->getStubHash(),
            ':code' => $flowException->getCode(),
            ':message' => $flowException->getMessage(),
            ':file' => $flowException->getFile(),
            ':line' => $flowException->getLine(),
            ':trace_string' => $flowException->getTraceString(),
            ':time' => $flowException->getTime()->format('Y-m-d H:i:s.u'),
        ]);
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
            ':time' => $flowResult->getTime()->format('Y-m-d H:i:s.u'),
        ]);
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
     * @param class-string $flowSource
     * @param class-string $messageSource
     * @param array<mixed> $message
     */
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, array $message, array $includeStubs = [], ?string $flowSubject = null): void
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
     * @return iterable<array<mixed>>
     */
    public function findAllSchemas(): iterable
    {
        $stmt = $this->client->query(
            'SELECT * FROM flow_schema'
        );

        if ($stmt === false) {
            return [];
        }

        foreach ($stmt->fetchAll() as $row) {
            $flowSchema = $row['flow_schema'];
            $flowSchemaArray = json_decode($flowSchema, true);
            if (!is_array($flowSchemaArray)) {
                throw new RuntimeException('Could not validate flow schema payload.');
            }

            yield $flowSchemaArray;
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
            return [];
        }

        foreach ($stmt->fetchAll() as $row) {
            $includeStubsRaw = $row['include_stubs'] ?? '[]';
            /** @var class-string[] $includeStubsParsed */
            $includeStubsParsed = is_string($includeStubsRaw) ? (json_decode($includeStubsRaw, true) ?? []) : [];

            yield new ObserveItem(
                queueId: $row['queue_id'] ?? '',
                type: $row['type'] ?? '',
                flowSubject: $row['flow_subject'] ?? null,
                flowSource: $row['flow_source'] ?? '',
                flowHash: $row['flow_hash'] ?? null,
                messageSource: $row['message_source'] ?? '',
                message: $row['message'] ?? [],
                includeStubs: $includeStubsParsed,
            );
        }
    }

    public function countFlows(): int
    {
        $stmt = $this->client->query('SELECT COUNT(*) FROM flow_instance');
        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    public function countFlowsBySource(string $flowSource = ''): int
    {
        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_instance WHERE flow_source = :flow_source');
        $stmt->bindValue(':flow_source', $flowSource);
        $stmt->execute();

        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    public function countFlowsByType(string $flowType = ''): int
    {
        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_instance WHERE flow_type LIKE :flow_type');
        $stmt->bindValue(':flow_type', $flowType . '.v%');
        $stmt->execute();

        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    public function countExceptions(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int
    {
        $conditions = [];
        $params = [];

        if ($from instanceof \DateTimeInterface) {
            $conditions[] = 'time >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s.u');
        }

        if ($to instanceof \DateTimeInterface) {
            $conditions[] = 'time <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s.u');
        }

        $sql = 'SELECT COUNT(*) FROM flow_exception';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->client->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function countExceptionsByFlowHash(string $flowHash = ''): int
    {
        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_exception WHERE flow_hash = :flow_hash');
        $stmt->bindValue(':flow_hash', $flowHash);
        $stmt->execute();

        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    public function countFlowsBySubject(string $flowSubject): int
    {
        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_instance WHERE flow_subject LIKE :flow_subject');
        $stmt->bindValue(':flow_subject', '%' . $flowSubject . '%');
        $stmt->execute();

        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    public function findAllFlows(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        $skip = max(0, $skip);
        $top = max(1, $top);

        $where = '';
        $params = [];
        if ($from instanceof DateTimeInterface) {
            $where .= ' AND fr.`time` >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to instanceof DateTimeInterface) {
            $where .= ' AND fr.`time` <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s');
        }

        $sql = 'SELECT fi.*, MAX(fr.`time`) AS time_last_run, COUNT(DISTINCT fe.flow_hash) AS exception_count FROM flow_instance fi' .
            ' JOIN flow_run fr ON fi.flow_hash = fr.flow_hash' .
            ' LEFT JOIN flow_exception fe ON fi.flow_hash = fe.flow_hash' .
            ' WHERE 1=1' .
            $where .
            ' GROUP BY fi.flow_hash' .
            ' ORDER BY time_last_run ' . $sortEnum->name . ', fi.flow_hash ' . $sortEnum->name .
            ' LIMIT ' . $skip . ', ' . $top;

        $stmt = $this->client->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            yield new FlowEntity(
                flowHash: $row['flow_hash'] ?? '',
                flowType: $row['flow_type'] ?? '',
                flowSource: $row['flow_source'] ?? '',
                flowSubject: $row['flow_subject'] ?? null,
                time: new DateTimeImmutable($row['time'] ?? 'now'),
                timeLastRun: new DateTimeImmutable($row['time_last_run'] ?? 'now'),
                exceptionCount: $row['exception_count'] ?? 0,
            );
        }
    }

    /**
     * @return FlowEntity[]
     * @throws Exception
     */
    public function findFlowsBySource(string $flowSource, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        if ($flowSource === '' || $flowSource === '*') {
            yield from $this->findAllFlows($sortEnum, $top, $skip, $from, $to);
            return;
        }

        $skip = max(0, $skip);
        $top = max(1, $top);

        $where = '';
        $params = [];
        if ($from instanceof DateTimeInterface) {
            $where .= ' AND fr.`time` >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to instanceof DateTimeInterface) {
            $where .= ' AND fr.`time` <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s');
        }

        $stmt = $this->client->prepare(
            'SELECT fi.*, MAX(fr.`time`) AS time_last_run, COUNT(DISTINCT fe.flow_hash) AS exception_count FROM flow_instance fi' .
            ' JOIN flow_run fr ON fi.flow_hash = fr.flow_hash' .
            ' LEFT JOIN flow_exception fe ON fi.flow_hash = fe.flow_hash' .
            ' WHERE fi.flow_source = :flow_source' .
            $where .
            ' GROUP BY fi.flow_hash' .
            ' ORDER BY time_last_run ' . $sortEnum->name . ', fi.flow_hash ' . $sortEnum->name . ' LIMIT :skip, :top'
        );
        $stmt->bindValue(':flow_source', $flowSource);
        $stmt->bindValue(':skip', $skip, Client::PARAM_INT);
        $stmt->bindValue(':top', $top, Client::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            yield new FlowEntity(
                flowHash: $row['flow_hash'] ?? '',
                flowType: $row['flow_type'] ?? '',
                flowSource: $row['flow_source'] ?? '',
                flowSubject: $row['flow_subject'] ?? null,
                time: new DateTimeImmutable($row['time'] ?? 'now'),
                timeLastRun: new DateTimeImmutable($row['time_last_run'] ?? 'now'),
                exceptionCount: $row['exception_count'] ?? 0,
            );
        }
    }

    public function findFlowsByType(string $flowType, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        if ($flowType === '' || $flowType === '*') {
            yield from $this->findAllFlows($sortEnum, $top, $skip, $from, $to);
            return;
        }

        $skip = max(0, $skip);
        $top = max(1, $top);

        $where = '';
        $params = [];
        if ($from instanceof DateTimeInterface) {
            $where .= ' AND fr.`time` >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to instanceof DateTimeInterface) {
            $where .= ' AND fr.`time` <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s');
        }

        $stmt = $this->client->prepare(
            'SELECT fi.*, MAX(fr.`time`) AS time_last_run, COUNT(DISTINCT fe.flow_hash) AS exception_count FROM flow_instance fi' .
            ' JOIN flow_run fr ON fi.flow_hash = fr.flow_hash' .
            ' LEFT JOIN flow_exception fe ON fi.flow_hash = fe.flow_hash' .
            ' WHERE fi.flow_type LIKE :flow_type' .
            $where .
            ' GROUP BY fi.flow_hash' .
            ' ORDER BY time_last_run ' . $sortEnum->name . ', fi.flow_hash ' . $sortEnum->name . ' LIMIT :skip, :top'
        );
        $stmt->bindValue(':flow_type', $flowType . '.v%');
        $stmt->bindValue(':skip', $skip, Client::PARAM_INT);
        $stmt->bindValue(':top', $top, Client::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            yield new FlowEntity(
                flowHash: $row['flow_hash'] ?? '',
                flowType: $row['flow_type'] ?? '',
                flowSource: $row['flow_source'] ?? '',
                flowSubject: $row['flow_subject'] ?? null,
                time: new DateTimeImmutable($row['time'] ?? 'now'),
                timeLastRun: new DateTimeImmutable($row['time_last_run'] ?? 'now'),
                exceptionCount: $row['exception_count'] ?? 0,
            );
        }
    }

    /**
     * @return FlowEntity[]
     * @throws Exception
     */
    public function findFlowsBySubject(string $flowSubject, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        $skip = max(0, $skip);
        $top = max(1, $top);

        $where = '';
        $params = [];
        if ($from instanceof DateTimeInterface) {
            $where .= ' AND fr.`time` >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to instanceof DateTimeInterface) {
            $where .= ' AND fr.`time` <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s');
        }

        $stmt = $this->client->prepare(
            'SELECT fi.*, MAX(fr.`time`) AS time_last_run, COUNT(DISTINCT fe.flow_hash) AS exception_count FROM flow_instance fi' .
            ' JOIN flow_run fr ON fi.flow_hash = fr.flow_hash' .
            ' LEFT JOIN flow_exception fe ON fi.flow_hash = fe.flow_hash' .
            ' WHERE fi.flow_subject LIKE :flow_subject' .
            $where .
            ' GROUP BY fi.flow_hash' .
            ' ORDER BY time_last_run ' . $sortEnum->name . ' LIMIT :skip, :top'
        );
        $stmt->bindValue(':flow_subject', '%' . $flowSubject . '%');
        $stmt->bindValue(':skip', $skip, Client::PARAM_INT);
        $stmt->bindValue(':top', $top, Client::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            yield new FlowEntity(
                flowHash: $row['flow_hash'] ?? '',
                flowType: $row['flow_type'] ?? '',
                flowSource: $row['flow_source'] ?? '',
                flowSubject: $row['flow_subject'] ?? null,
                time: new DateTimeImmutable($row['time'] ?? 'now'),
                timeLastRun: new DateTimeImmutable($row['time_last_run'] ?? 'now'),
                exceptionCount: $row['exception_count'] ?? 0,
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

        $where = '';
        $params = [];
        if ($from instanceof DateTimeInterface) {
            $where .= ' AND fi.`time` >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to instanceof DateTimeInterface) {
            $where .= ' AND fi.`time` <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s');
        }

        $sql = 'SELECT * FROM flow_exception fi' .
            ' WHERE 1=1' .
            $where .
            ' ORDER BY flow_hash ' . $sortEnum->name .
            ' LIMIT ' . $skip . ', ' . $top;

        $stmt = $this->client->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            /** @var array{flow_hash: string, flow_runtime_hash: string, flow_type: string, stub_source: class-string<StubInterface>, stub_hash: ?string, code: int, message: string, file: string, line: int, trace_string: string, time: string, hash: string} $row */
            yield new FlowException(
                flowHash: $row['flow_hash'],
                flowRuntimeHash: $row['flow_runtime_hash'],
                flowType: $row['flow_type'],
                stubSource: $row['stub_source'],
                stubHash: $row['stub_hash'],
                code: $row['code'],
                message: $row['message'],
                file: $row['file'],
                line: $row['line'],
                traceString: $row['trace_string'],
                time: new DateTimeImmutable($row['time']),
                hash: $row['hash'],
            );
        }
    }

    /**
     * @return FlowException[]
     * @throws Exception
     */
    public function findExceptionsByFlowHash(string $flowHash, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        if ($flowHash === '' || $flowHash === '*') {
            yield from $this->findAllExceptions($sortEnum, $top, $skip, $from, $to);
            return;
        }

        $skip = max(0, $skip);
        $top = max(1, $top);

        $where = '';
        $params = [];
        if ($from instanceof DateTimeInterface) {
            $where .= ' AND fi.`time` >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to instanceof DateTimeInterface) {
            $where .= ' AND fi.`time` <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s');
        }

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_exception fi' .
            ' WHERE flow_hash = :flow_hash' .
            $where .
            ' ORDER BY flow_hash ' . $sortEnum->name .
            ' LIMIT :skip, :top'
        );
        $stmt->bindValue(':flow_hash', $flowHash);
        $stmt->bindValue(':skip', $skip, Client::PARAM_INT);
        $stmt->bindValue(':top', $top, Client::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            /** @var array{flow_hash: string, flow_runtime_hash: string, flow_type: string, stub_source: class-string<StubInterface>, stub_hash: ?string, code: int, message: string, file: string, line: int, trace_string: string, time: string, hash: string} $row */
            yield new FlowException(
                flowHash: $row['flow_hash'],
                flowRuntimeHash: $row['flow_runtime_hash'],
                flowType: $row['flow_type'],
                stubSource: $row['stub_source'],
                stubHash: $row['stub_hash'],
                code: $row['code'],
                message: $row['message'],
                file: $row['file'],
                line: $row['line'],
                traceString: $row['trace_string'],
                time: new DateTimeImmutable($row['time']),
                hash: $row['hash'],
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
                'stubHash' => $message['stub_hash'] ?? null,
                'messageType' => $message['message_type'] ?? '',
                'messageSource' => $message['message_source'] ?? '',
                'message' => $messageArray,
                'predecessorHash' => $message['predecessor_hash'] ?? '',
                'time' => $message['time'] ?? 'now',
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

        foreach ($stmt->fetchAll() as $exception) {
            $flowArray['flowExceptions'][] = [
                'hash' => $exception['hash'] ?? '',
                'flowHash' => $exception['flow_hash'] ?? '',
                'flowRuntimeHash' => $exception['flow_runtime_hash'] ?? '',
                'stubSource' => $exception['stub_source'] ?? '',
                'stubHash' => $exception['stub_hash'] ?? null,
                'code' => $exception['code'] ?? 0,
                'message' => $exception['message'] ?? '',
                'file' => $exception['file'] ?? '',
                'line' => $exception['line'] ?? 0,
                'traceString' => $exception['trace_string'] ?? '',
                'time' => $exception['time'] ?? 'now',
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

        foreach ($stmt->fetchAll() as $result) {
            $flowArray['flowResults'][] = [
                'hash' => $result['hash'] ?? '',
                'flowHash' => $result['flow_hash'] ?? '',
                'flowRuntimeHash' => $result['flow_runtime_hash'] ?? '',
                'stubSource' => $result['stub_source'] ?? '',
                'stubHash' => $result['stub_hash'] ?? null,
                'result' => (bool) ($result['result'] ?? false),
                'time' => $result['time'] ?? 'now',
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

        foreach ($stmt->fetchAll() as $run) {
            $flowArray['flowRuns'][] = [
                'flowHash' => $run['flow_hash'] ?? '',
                'flowRuntimeHash' => $run['flow_runtime_hash'] ?? '',
                'flowType' => $run['flow_type'] ?? '',
                'time' => $run['time'] ?? 'now',
                'queueId' => $run['queue_id'] ?? '',
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
     * @return FlowStatsEntity[]
     */
    public function findFlowStats(?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $flowType = null): iterable
    {
        $where = '';
        $params = [];
        if ($from instanceof DateTimeInterface) {
            $where .= ' AND f.`time` >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s');
        }

        if ($to instanceof DateTimeInterface) {
            $where .= ' AND f.`time` <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s');
        }

        if ($flowType !== null && $flowType !== '') {
            $where .= ' AND f.flow_type LIKE :flow_type';
            $params[':flow_type'] = $flowType . '.v%';
        }

        // Run counts
        $stmt = $this->client->prepare(
            'SELECT DATE(f.`time`) AS `date`, COUNT(*) AS `count` FROM flow_run f' .
            ' WHERE 1=1' .
            $where .
            ' GROUP BY DATE(f.`time`)' .
            ' ORDER BY `date` ASC'
        );
        $stmt->execute($params);

        $runs = [];
        while ($row = $stmt->fetch(Client::FETCH_ASSOC)) {
            /** @var array{date: string, count: int|string} $row */
            $runs[$row['date']] = (int) $row['count'];
        }

        // Instance counts
        $stmt = $this->client->prepare(
            'SELECT DATE(f.`time`) AS `date`, COUNT(*) AS `count` FROM flow_instance f' .
            ' WHERE 1=1' .
            $where .
            ' GROUP BY DATE(f.`time`)' .
            ' ORDER BY `date` ASC'
        );
        $stmt->execute($params);

        $instances = [];
        while ($row = $stmt->fetch(Client::FETCH_ASSOC)) {
            /** @var array{date: string, count: int|string} $row */
            $instances[$row['date']] = (int) $row['count'];
        }

        $dates = array_unique(array_merge(array_keys($instances), array_keys($runs)));
        sort($dates);

        foreach ($dates as $date) {
            yield new FlowStatsEntity(
                date: $date,
                instances: $instances[$date] ?? 0,
                runs: $runs[$date] ?? 0,
            );
        }
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

        /** @var array{stub_hash: string, stub_source: class-string, source_content: string, time: string} $stubSource */
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

        foreach ($stmt->fetchAll() as $stubSource) {
            /** @var array{stub_hash: string, stub_source: class-string, source_content: string, time: string} $stubSource */
            yield new StubSourceEntity(
                stubHash: $stubSource['stub_hash'],
                stubSource: $stubSource['stub_source'],
                sourceContent: $stubSource['source_content'],
                time: new DateTimeImmutable($stubSource['time']),
            );
        }
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
                message: $messageArray,
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
