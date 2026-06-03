<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use PDO as Client;
use RuntimeException;
use Throwable;
use Wundii\Flowcrafter\Converter;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowResult;
use Wundii\Flowcrafter\FlowRetry;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StepInterface;
use Wundii\Flowcrafter\ObserverException;
use Wundii\Flowcrafter\Projection\ProjectionException;
use Wundii\Flowcrafter\Schedule\ScheduleException;
use Wundii\Flowcrafter\Storage\Config\MySqlStorageConfig;
use Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowSchemaEntity;
use Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity;
use Wundii\Flowcrafter\Storage\Entity\StepSourceEntity;

class MySqlStorage extends ServiceStorage
{
    public const TYPE_INSTANCE = 'flow_instance';

    public const TYPE_MESSAGE = 'flow_message';

    public const TYPE_EXCEPTION = 'flow_exception';

    public const TYPE_RESULT = 'flow_result';

    public const TYPE_RUN = 'flow_run';

    public const TYPE_SCHEMA = 'flow_schema';

    public const TYPE_SOURCE_MESSAGE = 'flow_source_message';

    public const TYPE_SOURCE_STEP = 'flow_source_step';

    protected Client $client;

    public function __construct(MySqlStorageConfig $mySqlStorageConfig, ?string $sqliteFile = null)
    {
        parent::__construct($sqliteFile);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $mySqlStorageConfig->getHost(),
            $mySqlStorageConfig->getPort(),
            $mySqlStorageConfig->getDatabase(),
        );
        $this->client = new Client(
            $dsn,
            $mySqlStorageConfig->getUsername(),
            $mySqlStorageConfig->getPassword(),
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
            CREATE TABLE IF NOT EXISTS flow_source_step (
                step_hash VARCHAR(191) NOT NULL PRIMARY KEY,
                step_source VARCHAR(191) NOT NULL,
                source_content TEXT NOT NULL,
                time DATETIME(3) NOT NULL,
                INDEX flow_source_step_hash (step_hash)
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
                flow_type VARCHAR(255) NOT NULL,
                step_source VARCHAR(255) NOT NULL,
                step_hash VARCHAR(191) NOT NULL,
                message_type VARCHAR(64) NOT NULL,
                message_source VARCHAR(255) NOT NULL,
                message_hash VARCHAR(32) NOT NULL,
                message JSON NOT NULL,
                predecessor_hash VARCHAR(191) NULL,
                `time` DATETIME(3) NOT NULL,
                INDEX idx_flow_message_flow_hash (flow_hash),
                INDEX idx_flow_message_flow_runtime_hash (flow_runtime_hash),
                INDEX idx_flow_message_flow_type (flow_type),
                INDEX idx_flow_message_message_source (message_source),
                INDEX idx_flow_message_message_type (message_type),
                FOREIGN KEY (flow_hash) REFERENCES flow_instance(flow_hash),
                FOREIGN KEY (flow_runtime_hash) REFERENCES flow_run(flow_runtime_hash),
                FOREIGN KEY (step_hash) REFERENCES flow_source_step(step_hash)
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
                step_source VARCHAR(255) NOT NULL,
                step_hash VARCHAR(191) NOT NULL,
                code VARCHAR(11) NOT NULL,
                message VARCHAR(2000) NOT NULL,
                file VARCHAR(2000) NOT NULL,
                line INT(11) NOT NULL,
                trace_string TEXT NOT NULL,
                `time` DATETIME(3) NOT NULL,
                INDEX idx_flow_exception_flow_hash (flow_hash),
                INDEX idx_flow_exception_flow_runtime_hash (flow_runtime_hash),
                INDEX idx_flow_exception_step_source (step_source),
                FOREIGN KEY (flow_hash) REFERENCES flow_instance(flow_hash),
                FOREIGN KEY (flow_runtime_hash) REFERENCES flow_run(flow_runtime_hash),
                FOREIGN KEY (step_hash) REFERENCES flow_source_step(step_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_result (
                hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_hash VARCHAR(191) NOT NULL,
                flow_runtime_hash VARCHAR(191) NOT NULL,
                step_source VARCHAR(255) NOT NULL,
                step_hash VARCHAR(191) NULL,
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
            CREATE TABLE IF NOT EXISTS flow_step_retry (
                hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_hash VARCHAR(191) NOT NULL,
                flow_runtime_hash VARCHAR(191) NOT NULL,
                step_source VARCHAR(255) NOT NULL,
                attempt INT NOT NULL,
                message TEXT NOT NULL,
                `time` DATETIME(3) NOT NULL,
                INDEX idx_flow_step_retry_flow_hash (flow_hash),
                INDEX idx_flow_step_retry_flow_runtime_hash (flow_runtime_hash),
                FOREIGN KEY (flow_hash) REFERENCES flow_instance(flow_hash),
                FOREIGN KEY (flow_runtime_hash) REFERENCES flow_run(flow_runtime_hash)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_schedule_exception (
                hash VARCHAR(191) NOT NULL PRIMARY KEY,
                schedule_class VARCHAR(255) NOT NULL,
                schedule_name VARCHAR(255) NOT NULL,
                schedule_expression VARCHAR(100) NOT NULL,
                code VARCHAR(11) NOT NULL,
                message VARCHAR(2000) NOT NULL,
                file VARCHAR(2000) NOT NULL,
                line INT(11) NOT NULL,
                trace_string TEXT NOT NULL,
                `time` DATETIME(3) NOT NULL,
                INDEX idx_flow_schedule_exception_time (time)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_observer_exception (
                hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_source VARCHAR(255) NOT NULL,
                message_source VARCHAR(255) NOT NULL,
                queue_id VARCHAR(191) NULL,
                code VARCHAR(11) NOT NULL,
                message VARCHAR(2000) NOT NULL,
                file VARCHAR(2000) NOT NULL,
                line INT(11) NOT NULL,
                trace_string TEXT NOT NULL,
                `time` DATETIME(3) NOT NULL,
                INDEX idx_flow_observer_exception_time (time)
            )
            SQL
        );

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_projection_exception (
                hash VARCHAR(191) NOT NULL PRIMARY KEY,
                flow_hash VARCHAR(191) NOT NULL,
                flow_type VARCHAR(191) NOT NULL,
                projection_handler_class VARCHAR(255) NOT NULL,
                code VARCHAR(11) NOT NULL,
                message VARCHAR(2000) NOT NULL,
                file VARCHAR(2000) NOT NULL,
                line INT(11) NOT NULL,
                trace_string TEXT NOT NULL,
                `time` DATETIME(3) NOT NULL,
                INDEX idx_flow_projection_exception_time (time)
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
            throw new InvalidArgumentException('The flow hash has not yet been registered, but the flow type "' . $flowSchema->type() . '" already exists.');
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

    public function registerStepSource(StepSourceEntity $stepSourceEntity): void
    {
        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_source_step (step_hash, step_source, source_content, time) ' .
            'VALUES (:step_hash, :step_source, :source_content, :time)'
        );
        $stmt->execute([
            ':step_hash' => $stepSourceEntity->stepHash,
            ':step_source' => $stepSourceEntity->stepSource,
            ':source_content' => $stepSourceEntity->sourceContent,
            ':time' => $stepSourceEntity->time->format('Y-m-d H:i:s.v'),
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
            'INSERT IGNORE INTO flow_message (hash, flow_hash, flow_runtime_hash, flow_type, step_source, step_hash, message_hash, message_type, message_source, predecessor_hash, time, message) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :flow_type, :step_source, :step_hash, :message_hash, :message_type, :message_source, :predecessor_hash, :time, :message)'
        );

        $stmt->execute([
            ':hash' => $flowMessage->getHash(),
            ':flow_hash' => $flowMessage->getFlowHash(),
            ':flow_runtime_hash' => $flowMessage->getFlowRuntimeHash(),
            ':flow_type' => $flowMessage->getFlowType(),
            ':step_source' => $flowMessage->getStepSource(),
            ':step_hash' => $flowMessage->getStepHash(),
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
            'INSERT IGNORE INTO flow_exception (hash, flow_hash, flow_runtime_hash, flow_type, step_source, step_hash, code, message, file, line, trace_string, time) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :flow_type, :step_source, :step_hash, :code, :message, :file, :line, :trace_string, :time)'
        );

        $stmt->execute([
            ':hash' => $flowException->getHash(),
            ':flow_hash' => $flowException->getFlowHash(),
            ':flow_runtime_hash' => $flowException->getFlowRuntimeHash(),
            ':flow_type' => $flowException->getFlowType(),
            ':step_source' => $flowException->getStepSource(),
            ':step_hash' => $flowException->getStepHash(),
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
            'INSERT IGNORE INTO flow_schedule_exception (hash, schedule_class, schedule_name, schedule_expression, code, message, file, line, trace_string, time) ' .
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
            'INSERT IGNORE INTO flow_observer_exception (hash, flow_source, message_source, queue_id, code, message, file, line, trace_string, time) ' .
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

    public function appendProjectionException(ProjectionException $projectionException): void
    {
        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_projection_exception (hash, flow_hash, flow_type, projection_handler_class, code, message, file, line, trace_string, time) ' .
            'VALUES (:hash, :flow_hash, :flow_type, :projection_handler_class, :code, :message, :file, :line, :trace_string, :time)'
        );

        $stmt->execute([
            ':hash' => $projectionException->getHash(),
            ':flow_hash' => $projectionException->getFlowHash(),
            ':flow_type' => $projectionException->getFlowType(),
            ':projection_handler_class' => $projectionException->getProjectionHandlerClass(),
            ':code' => $projectionException->getCode(),
            ':message' => $projectionException->getMessage(),
            ':file' => $projectionException->getFile(),
            ':line' => $projectionException->getLine(),
            ':trace_string' => $projectionException->getTraceString(),
            ':time' => $projectionException->getTime()->format('Y-m-d H:i:s.v'),
        ]);

        parent::appendProjectionException($projectionException);
    }

    public function appendFlowResult(FlowResult $flowResult): void
    {
        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_result (hash, flow_hash, flow_runtime_hash, step_source, step_hash, result, time) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :step_source, :step_hash, :result, :time)'
        );

        $stmt->execute([
            ':hash' => $flowResult->getHash(),
            ':flow_hash' => $flowResult->getFlowHash(),
            ':flow_runtime_hash' => $flowResult->getFlowRuntimeHash(),
            ':step_source' => $flowResult->getStepSource(),
            ':step_hash' => $flowResult->getStepHash(),
            ':result' => $flowResult->getResult() ? 1 : 0,
            ':time' => $flowResult->getTime()->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function appendFlowRetry(FlowRetry $flowRetry): void
    {
        $stmt = $this->client->prepare(
            'INSERT IGNORE INTO flow_step_retry (hash, flow_hash, flow_runtime_hash, step_source, attempt, message, time) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :step_source, :attempt, :message, :time)'
        );

        $stmt->execute([
            ':hash' => $flowRetry->getHash(),
            ':flow_hash' => $flowRetry->getFlowHash(),
            ':flow_runtime_hash' => $flowRetry->getFlowRuntimeHash(),
            ':step_source' => $flowRetry->getStepSource(),
            ':attempt' => $flowRetry->getAttempt(),
            ':message' => $flowRetry->getMessage(),
            ':time' => $flowRetry->getTime()->format('Y-m-d H:i:s.v'),
        ]);
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

            $steps = $flowSchemaArray['steps'] ?? null;
            if (!is_array($steps)) {
                throw new RuntimeException('Could not validate flow schema steps.');
            }

            yield new FlowSchemaEntity(
                $row['flow_schema_hash'],
                $row['flow_schema_type'],
                $steps,
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
        /** @var class-string<FlowInterface> $flowSource */
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
            /** @var array{hash: string, flow_hash: string, flow_runtime_hash: string, flow_type: string, step_source: string, step_hash: string|null, message_type: string, message_source: string, message_hash: string, message: string, predecessor_hash: string, time: string} $message */
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
                'flowType' => $message['flow_type'],
                'stepSource' => $message['step_source'],
                'stepHash' => $message['step_hash'],
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
            /** @var array{hash: string, flow_hash: string, flow_runtime_hash: string, flow_type: string, step_source: string, step_hash: string|null, code: string, message: string, file: string, line: int|string, trace_string: string, time: string} $exception */
            $flowArray['flowExceptions'][] = [
                'hash' => $exception['hash'],
                'flowHash' => $exception['flow_hash'],
                'flowRuntimeHash' => $exception['flow_runtime_hash'],
                'flowType' => $exception['flow_type'],
                'stepSource' => $exception['step_source'],
                'stepHash' => $exception['step_hash'],
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
            /** @var array{hash: string, flow_hash: string, flow_runtime_hash: string, step_source: string, step_hash: string|null, result: int|string, time: string} $result */
            $flowArray['flowResults'][] = [
                'hash' => $result['hash'],
                'flowHash' => $result['flow_hash'],
                'flowRuntimeHash' => $result['flow_runtime_hash'],
                'stepSource' => $result['step_source'],
                'stepHash' => $result['step_hash'],
                'result' => (bool) $result['result'],
                'time' => $result['time'],
            ];
        }

        $stmt = $this->client->prepare(
            'SELECT * FROM flow_step_retry ' .
            'WHERE flow_hash = :flow_hash ' .
            'ORDER BY time ASC'
        );
        $stmt->execute([
            ':flow_hash' => $flowHash,
        ]);

        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $retry) {
            /** @var array{hash: string, flow_hash: string, flow_runtime_hash: string, step_source: string, attempt: int|string, message: string, time: string} $retry */
            $flowArray['flowRetries'][] = [
                'hash' => $retry['hash'],
                'flowHash' => $retry['flow_hash'],
                'flowRuntimeHash' => $retry['flow_runtime_hash'],
                'stepSource' => $retry['step_source'],
                'attempt' => (int) $retry['attempt'],
                'message' => $retry['message'],
                'time' => $retry['time'],
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
    public function findStepSourceByHash(string $stepHash): ?StepSourceEntity
    {
        $stmt = $this->client->prepare(
            'SELECT * FROM flow_source_step ' .
            'WHERE step_hash = :step_hash'
        );
        $stmt->execute([
            ':step_hash' => $stepHash,
        ]);

        $stepSource = $stmt->fetch();
        if ($stepSource === false) {
            return null;
        }

        /** @var array{step_hash: string, step_source: class-string<StepInterface>, source_content: string, time: string} $stepSource */
        return new StepSourceEntity(
            stepHash: $stepSource['step_hash'],
            stepSource: $stepSource['step_source'],
            sourceContent: $stepSource['source_content'],
            time: new DateTimeImmutable($stepSource['time']),
        );
    }

    /**
     * @param class-string $stepSource
     * @return iterable<StepSourceEntity>
     */
    public function findStepSourcesByStepSource(string $stepSource): iterable
    {
        $stmt = $this->client->prepare(
            'SELECT * FROM flow_source_step ' .
            'WHERE step_source = :step_source ' .
            'ORDER BY `time` ASC'
        );
        $stmt->execute([
            ':step_source' => $stepSource,
        ]);

        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $stepSource) {
            /** @var array{step_hash: string, step_source: class-string<StepInterface>, source_content: string, time: string} $stepSource */
            yield new StepSourceEntity(
                stepHash: $stepSource['step_hash'],
                stepSource: $stepSource['step_source'],
                sourceContent: $stepSource['source_content'],
                time: new DateTimeImmutable($stepSource['time']),
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

}
