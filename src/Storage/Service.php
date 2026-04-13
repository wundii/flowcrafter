<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use DateTimeImmutable;
use DateTimeInterface;
use PDO as Client;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Schedule\ScheduleException;
use Wundii\Flowcrafter\Storage\Entity\ExceptionListEntity;
use Wundii\Flowcrafter\Storage\Entity\ExceptionStatsEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowListEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowStatsEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowTypeStatsEntity;

abstract class Service implements StorageInterface
{
    private Client $client;

    public function __construct(?string $file = null)
    {
        $dns = 'sqlite::memory:?cache=shared';

        if ($file !== null) {
            $directory = dirname($file);
            if (!is_dir($directory)) {
                $fileSystem = new Filesystem();
                $fileSystem->mkdir($directory, 0775);
            }

            $dns = 'sqlite:' . $file;
        }

        $this->client = new Client(
            dsn: $dns,
            options: [
                Client::ATTR_ERRMODE => Client::ERRMODE_EXCEPTION,
            ],
        );
    }

    public function initializeDatabase(): void
    {
        $this->client->exec('PRAGMA journal_mode = WAL;');
        $this->client->exec('PRAGMA synchronous = NORMAL;');
        $this->client->exec('PRAGMA temp_store = MEMORY;');
        $this->client->exec('PRAGMA busy_timeout = 5000;');
        $this->client->exec('PRAGMA foreign_keys = ON;');

        $this->client->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS flow_list (
                flow_hash TEXT PRIMARY KEY,
                flow_type TEXT NOT NULL,
                flow_source TEXT NOT NULL,
                flow_subject TEXT,
                flow_time TEXT NOT NULL,
                last_term TEXT NOT NULL,
                status TEXT NOT NULL
            );

            CREATE INDEX IF NOT EXISTS flow_list_type ON flow_list(flow_type);
            CREATE INDEX IF NOT EXISTS flow_list_source ON flow_list(flow_source);
            CREATE INDEX IF NOT EXISTS flow_list_subject ON flow_list(flow_subject);
            CREATE INDEX IF NOT EXISTS flow_list_status ON flow_list(status);

            CREATE TABLE IF NOT EXISTS flow_run_list (
                flow_runtime_hash TEXT PRIMARY KEY,
                flow_hash TEXT NOT NULL,
                flow_type TEXT NOT NULL,
                flow_time TEXT NOT NULL
            );

            CREATE INDEX IF NOT EXISTS flow_run_list_hash ON flow_run_list(flow_hash);
            CREATE INDEX IF NOT EXISTS flow_run_list_type ON flow_run_list(flow_type);

            CREATE TABLE IF NOT EXISTS flow_exception_list (
                hash TEXT PRIMARY KEY,
                flow_hash TEXT NOT NULL,
                flow_runtime_hash TEXT NOT NULL,
                flow_type TEXT NOT NULL,
                stub_source TEXT NOT NULL,
                stub_hash TEXT NOT NULL,
                code INTEGER NOT NULL,
                message TEXT NOT NULL,
                file TEXT NOT NULL,
                line INTEGER NOT NULL,
                trace_string TEXT NOT NULL,
                time TEXT NOT NULL
            );

            CREATE INDEX IF NOT EXISTS flow_exception_list_flow_hash ON flow_exception_list(flow_hash);
            CREATE INDEX IF NOT EXISTS flow_exception_list_time ON flow_exception_list(time);

            CREATE TABLE IF NOT EXISTS schedule_exception_list (
                hash TEXT PRIMARY KEY,
                schedule_class TEXT NOT NULL,
                schedule_name TEXT NOT NULL,
                schedule_expression TEXT NOT NULL,
                code INTEGER NOT NULL,
                message TEXT NOT NULL,
                file TEXT NOT NULL,
                line INTEGER NOT NULL,
                trace_string TEXT NOT NULL,
                time TEXT NOT NULL
            );

            CREATE INDEX IF NOT EXISTS schedule_exception_list_time ON schedule_exception_list(time);
            SQL
        );
    }

    public function isServiceStorageInitialized(): bool
    {
        try {
            $stmt = $this->client->query(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' " .
                "AND name IN ('flow_list', 'flow_run_list', 'flow_exception_list', 'schedule_exception_list')"
            );
            if ($stmt === false) {
                return false;
            }

            return (int) $stmt->fetchColumn() === 4;
        } catch (Throwable) {
            return false;
        }
    }

    public function appendFlow(Flow $flow): void
    {
        $stmt = $this->client->prepare(
            'INSERT INTO flow_list (flow_hash, flow_type, flow_source, flow_subject, flow_time, last_term, status) ' .
            'VALUES (:flow_hash, :flow_type, :flow_source, :flow_subject, :flow_time, :last_term, :status) ' .
            'ON CONFLICT(flow_hash) DO UPDATE SET ' .
            'last_term = excluded.last_term, ' .
            'status = excluded.status'
        );

        $runs = $flow->runs();
        $lastRun = end($runs);

        $stmt->execute([
            ':flow_hash' => $flow->getHash(),
            ':flow_type' => $flow->getType(),
            ':flow_source' => $flow->getSource(),
            ':flow_subject' => $flow->getSubject(),
            ':flow_time' => $flow->getTime()->format('Y-m-d H:i:s.u'),
            ':last_term' => $lastRun !== false ? $lastRun->getTime()->format('Y-m-d H:i:s.u') : $flow->getTime()->format('Y-m-d H:i:s.u'),
            ':status' => $flow->status()->name,
        ]);

        $stmt = $this->client->prepare(
            'INSERT OR IGNORE INTO flow_run_list (flow_runtime_hash, flow_hash, flow_type, flow_time) ' .
            'VALUES (:flow_runtime_hash, :flow_hash, :flow_type, :flow_time)'
        );

        foreach ($flow->runs() as $flowRun) {
            $stmt->execute([
                ':flow_runtime_hash' => $flowRun->getFlowRuntimeHash(),
                ':flow_hash' => $flowRun->getFlowHash(),
                ':flow_type' => $flowRun->getFlowType(),
                ':flow_time' => $flowRun->getTime()->format('Y-m-d H:i:s.u'),
            ]);
        }

        $stmt = $this->client->prepare(
            'INSERT OR IGNORE INTO flow_exception_list (hash, flow_hash, flow_runtime_hash, flow_type, stub_source, stub_hash, code, message, file, line, trace_string, time) ' .
            'VALUES (:hash, :flow_hash, :flow_runtime_hash, :flow_type, :stub_source, :stub_hash, :code, :message, :file, :line, :trace_string, :time)'
        );

        foreach ($flow->getFlowExceptions() as $flowException) {
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
                ':time' => $flowException->getTime()->format('Y-m-d H:i:s.u'),
            ]);
        }
    }

    public function appendScheduleException(ScheduleException $scheduleException): void
    {
        $stmt = $this->client->prepare(
            'INSERT OR IGNORE INTO schedule_exception_list ' .
            '(hash, schedule_class, schedule_name, schedule_expression, code, message, file, line, trace_string, time) ' .
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
            ':time' => $scheduleException->getTime()->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function countExceptions(?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): int
    {
        [$where, $params] = $this->buildDateFilter($from, $to, 'fel.time');

        [$where, $params] = $this->applyStatusFilter($where, $params, $status);

        $stmt = $this->client->prepare(
            'SELECT COUNT(*) FROM flow_exception_list fel' .
            ' JOIN flow_list fl ON fel.flow_hash = fl.flow_hash' . $where
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function countScheduleExceptions(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int
    {
        [$where, $params] = $this->buildDateFilter($from, $to, 'time');

        $stmt = $this->client->prepare('SELECT COUNT(*) FROM schedule_exception_list' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function countFlows(?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): int
    {
        [$where, $params] = $this->buildDateFilter($from, $to);
        [$where, $params] = $this->applyStatusFilter($where, $params, $status);

        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_list' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function countFlowsBySource(string $flowSource, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): int
    {
        [$where, $params] = $this->buildDateFilter($from, $to);
        [$where, $params] = $this->applyStatusFilter($where, $params, $status);

        $where = $where === ''
            ? ' WHERE flow_source = :flow_source'
            : $where . ' AND flow_source = :flow_source';
        $params[':flow_source'] = $flowSource;

        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_list' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function countFlowsByType(string $flowType, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): int
    {
        [$where, $params] = $this->buildDateFilter($from, $to);
        [$where, $params] = $this->applyStatusFilter($where, $params, $status);

        $where = $where === ''
            ? ' WHERE flow_type LIKE :flow_type'
            : $where . ' AND flow_type LIKE :flow_type';
        $params[':flow_type'] = $flowType . '.v%';

        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_list' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function countFlowsBySubject(string $flowSubject, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int
    {
        [$where, $params] = $this->buildDateFilter($from, $to);

        $where = $where === ''
            ? ' WHERE flow_subject LIKE :flow_subject'
            : $where . ' AND flow_subject LIKE :flow_subject';
        $params[':flow_subject'] = '%' . $flowSubject . '%';

        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_list' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return ExceptionListEntity[]
     */
    public function findAllExceptions(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): iterable
    {
        $params = [];
        $flowWhere = '';
        $scheduleWhere = '';

        if ($from instanceof DateTimeInterface && $to instanceof DateTimeInterface) {
            $flowWhere = ' WHERE fel.time BETWEEN :from AND :to';
            $scheduleWhere = ' WHERE time BETWEEN :from2 AND :to2';
            $params[':from'] = $from->format('Y-m-d H:i:s.u');
            $params[':to'] = $to->format('Y-m-d H:i:s.u');
            $params[':from2'] = $from->format('Y-m-d H:i:s.u');
            $params[':to2'] = $to->format('Y-m-d H:i:s.u');
        } elseif ($from instanceof DateTimeInterface) {
            $flowWhere = ' WHERE fel.time >= :from';
            $scheduleWhere = ' WHERE time >= :from2';
            $params[':from'] = $from->format('Y-m-d H:i:s.u');
            $params[':from2'] = $from->format('Y-m-d H:i:s.u');
        } elseif ($to instanceof DateTimeInterface) {
            $flowWhere = ' WHERE fel.time <= :to';
            $scheduleWhere = ' WHERE time <= :to2';
            $params[':to'] = $to->format('Y-m-d H:i:s.u');
            $params[':to2'] = $to->format('Y-m-d H:i:s.u');
        }

        [$flowWhere, $params] = $this->applyStatusFilter($flowWhere, $params, $status);

        $sql =
            "SELECT 'flow' AS exception_type," .
            ' fel.hash, fel.code, fel.message, fel.file, fel.line, fel.trace_string, fel.time,' .
            ' fel.flow_hash, fel.flow_runtime_hash, fel.flow_type, fel.stub_source, fel.stub_hash,' .
            ' fl.status AS flow_status,' .
            ' NULL AS schedule_class, NULL AS schedule_name, NULL AS schedule_expression' .
            ' FROM flow_exception_list fel' .
            ' JOIN flow_list fl ON fel.flow_hash = fl.flow_hash' . $flowWhere .
            ' UNION ALL' .
            " SELECT 'schedule' AS exception_type," .
            ' hash, code, message, file, line, trace_string, time,' .
            ' NULL, NULL, NULL, NULL, NULL,' .
            ' NULL AS flow_status,' .
            ' schedule_class, schedule_name, schedule_expression' .
            ' FROM schedule_exception_list' . $scheduleWhere .
            ' ORDER BY time ' . $sortEnum->name .
            ' LIMIT :top OFFSET :skip';

        $params[':top'] = max(1, $top);
        $params[':skip'] = max(0, $skip);

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);
        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $row) {
            /** @var array{exception_type: string, hash: string, code: int|string, message: string, file: string, line: int|string, trace_string: string, time: string, flow_hash: string|null, flow_runtime_hash: string|null, flow_type: string|null, stub_source: string|null, stub_hash: string|null, flow_status: string|null, schedule_class: string|null, schedule_name: string|null, schedule_expression: string|null} $row */
            yield new ExceptionListEntity(
                type: $row['exception_type'],
                hash: $row['hash'],
                code: (int) $row['code'],
                message: $row['message'],
                file: $row['file'],
                line: (int) $row['line'],
                traceString: $row['trace_string'],
                time: new DateTimeImmutable($row['time']),
                flowHash: $row['flow_hash'],
                flowRuntimeHash: $row['flow_runtime_hash'],
                flowType: $row['flow_type'],
                stubSource: $row['stub_source'],
                stubHash: $row['stub_hash'],
                flowStatus: $row['flow_status'] !== null ? StatusEnum::fromName($row['flow_status']) : null,
                scheduleClass: $row['schedule_class'],
                scheduleName: $row['schedule_name'],
                scheduleExpression: $row['schedule_expression'],
            );
        }
    }

    /**
     * @return FlowListEntity[]
     */
    public function findAllFlows(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): iterable
    {
        [$where, $params] = $this->buildDateFilter($from, $to);
        [$where, $params] = $this->applyStatusFilter($where, $params, $status);

        $sql = 'SELECT * FROM flow_list' . $where .
            ' ORDER BY last_term ' . $sortEnum->name .
            ' LIMIT :top OFFSET :skip';

        $params[':top'] = $top;
        $params[':skip'] = $skip;

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);
        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $row) {
            /** @var array{flow_hash: string, flow_type: string, flow_source: string, flow_subject: string|null, flow_time: string, last_term: string, status: string} $row */
            yield new FlowListEntity(
                flowHash: $row['flow_hash'],
                flowType: $row['flow_type'],
                flowSource: $row['flow_source'],
                flowSubject: $row['flow_subject'],
                flowTime: new DateTimeImmutable($row['flow_time']),
                lastTerm: new DateTimeImmutable($row['last_term']),
                statusEnum: StatusEnum::fromName($row['status']),
            );
        }
    }

    /**
     * @return FlowListEntity[]
     */
    public function findFlowsBySource(string $flowSource, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): iterable
    {
        [$where, $params] = $this->buildDateFilter($from, $to);
        [$where, $params] = $this->applyStatusFilter($where, $params, $status);

        $where = $where === ''
            ? ' WHERE flow_source = :flow_source'
            : $where . ' AND flow_source = :flow_source';
        $params[':flow_source'] = $flowSource;

        $sql = 'SELECT * FROM flow_list' . $where .
            ' ORDER BY last_term ' . $sortEnum->name .
            ' LIMIT :top OFFSET :skip';

        $params[':top'] = $top;
        $params[':skip'] = $skip;

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);
        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $row) {
            /** @var array{flow_hash: string, flow_type: string, flow_source: string, flow_subject: string|null, flow_time: string, last_term: string, status: string} $row */
            yield new FlowListEntity(
                flowHash: $row['flow_hash'],
                flowType: $row['flow_type'],
                flowSource: $row['flow_source'],
                flowSubject: $row['flow_subject'],
                flowTime: new DateTimeImmutable($row['flow_time']),
                lastTerm: new DateTimeImmutable($row['last_term']),
                statusEnum: StatusEnum::fromName($row['status']),
            );
        }
    }

    /**
     * @return FlowListEntity[]
     */
    public function findFlowsByType(string $flowType, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $status = null): iterable
    {
        [$where, $params] = $this->buildDateFilter($from, $to);
        [$where, $params] = $this->applyStatusFilter($where, $params, $status);

        $where = $where === ''
            ? ' WHERE flow_type LIKE :flow_type'
            : $where . ' AND flow_type LIKE :flow_type';
        $params[':flow_type'] = $flowType . '.v%';

        $sql = 'SELECT * FROM flow_list' . $where .
            ' ORDER BY last_term ' . $sortEnum->name .
            ' LIMIT :top OFFSET :skip';

        $params[':top'] = $top;
        $params[':skip'] = $skip;

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);
        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $row) {
            /** @var array{flow_hash: string, flow_type: string, flow_source: string, flow_subject: string|null, flow_time: string, last_term: string, status: string} $row */
            yield new FlowListEntity(
                flowHash: $row['flow_hash'],
                flowType: $row['flow_type'],
                flowSource: $row['flow_source'],
                flowSubject: $row['flow_subject'],
                flowTime: new DateTimeImmutable($row['flow_time']),
                lastTerm: new DateTimeImmutable($row['last_term']),
                statusEnum: StatusEnum::fromName($row['status']),
            );
        }
    }

    /**
     * @return FlowListEntity[]
     */
    public function findFlowsBySubject(string $flowSubject, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        [$where, $params] = $this->buildDateFilter($from, $to);

        $where = $where === '' ? ' WHERE flow_subject LIKE :flow_subject' : $where . ' AND flow_subject LIKE :flow_subject';
        $params[':flow_subject'] = '%' . $flowSubject . '%';
        $params[':flow_subject_exact'] = $flowSubject;
        $params[':flow_subject_prefix'] = $flowSubject . '%';

        $sql = 'SELECT * FROM flow_list' . $where .
            ' ORDER BY CASE WHEN flow_subject = :flow_subject_exact THEN 0 WHEN flow_subject LIKE :flow_subject_prefix THEN 1 ELSE 2 END,' .
            ' last_term ' . $sortEnum->name .
            ' LIMIT :top OFFSET :skip';

        $params[':top'] = $top;
        $params[':skip'] = $skip;

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);
        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $row) {
            /** @var array{flow_hash: string, flow_type: string, flow_source: string, flow_subject: string|null, flow_time: string, last_term: string, status: string} $row */
            yield new FlowListEntity(
                flowHash: $row['flow_hash'],
                flowType: $row['flow_type'],
                flowSource: $row['flow_source'],
                flowSubject: $row['flow_subject'],
                flowTime: new DateTimeImmutable($row['flow_time']),
                lastTerm: new DateTimeImmutable($row['last_term']),
                statusEnum: StatusEnum::fromName($row['status']),
            );
        }
    }

    /**
     * @return iterable<FlowStatsEntity>
     */
    public function findFlowStats(?DateTimeInterface $from = null, ?DateTimeInterface $to = null, ?string $flowType = null): iterable
    {
        $where = '1=1';
        $params = [];

        if ($from instanceof DateTimeInterface) {
            $where .= ' AND flow_time >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s.u');
        }

        if ($to instanceof DateTimeInterface) {
            $where .= ' AND flow_time <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s.u');
        }

        if ($flowType !== null && $flowType !== '') {
            $where .= ' AND flow_type LIKE :flow_type';
            $params[':flow_type'] = $flowType . '.v%';
        }

        $instances = [];
        $stmt = $this->client->prepare(
            'SELECT DATE(flow_time) AS date, COUNT(*) AS count FROM flow_list WHERE ' . $where .
            ' GROUP BY DATE(flow_time) ORDER BY date ASC'
        );
        $stmt->execute($params);
        while ($row = $stmt->fetch(Client::FETCH_ASSOC)) {
            /** @var array{date: string, count: int|string} $row */
            $instances[$row['date']] = (int) $row['count'];
        }

        $runs = [];
        $stmt = $this->client->prepare(
            'SELECT DATE(flow_time) AS date, COUNT(*) AS count FROM flow_run_list WHERE ' . $where .
            ' GROUP BY DATE(flow_time) ORDER BY date ASC'
        );
        $stmt->execute($params);
        while ($row = $stmt->fetch(Client::FETCH_ASSOC)) {
            /** @var array{date: string, count: int|string} $row */
            $runs[$row['date']] = (int) $row['count'];
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
     * @return FlowTypeStatsEntity[]
     */
    public function findFlowTypeStats(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        [$where, $params] = $this->buildDateFilter($from, $to, 'last_term');

        $sql = <<<'SQL'
            SELECT
                flow_type,
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('FAILED', 'WARNING', 'IN_PROGRESS_EXCEEDED') THEN 1 ELSE 0 END) AS failed,
                MAX(last_term) AS last_time
            FROM flow_list
            SQL;

        $sql .= $where . ' GROUP BY flow_type ORDER BY flow_type ASC';

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);
        $stmt->setFetchMode(Client::FETCH_ASSOC);

        foreach ($stmt as $row) {
            /** @var array{flow_type: string, total: int|string, failed: int|string, last_time: string|null} $row */
            $total = (int) $row['total'];
            $failed = (int) $row['failed'];
            $prefix = (string) preg_replace('/\.v\d+$/', '', $row['flow_type']);

            yield new FlowTypeStatsEntity(
                prefix: $prefix,
                flowType: $row['flow_type'],
                total: $total,
                failed: $failed,
                successRate: $total > 0 ? (int) round((($total - $failed) / $total) * 100) : null,
                lastTime: $row['last_time'] ? new DateTimeImmutable($row['last_time']) : null,
            );
        }
    }

    /**
     * @return ExceptionStatsEntity[]
     */
    public function findExceptionStats(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        [$where, $params] = $this->buildDateFilter($from, $to, 'time');

        $flowCounts = [];
        $stmt = $this->client->prepare(
            'SELECT DATE(time) AS date, COUNT(*) AS count FROM flow_exception_list' . $where .
            ' GROUP BY DATE(time) ORDER BY date ASC'
        );
        $stmt->execute($params);
        while ($row = $stmt->fetch(Client::FETCH_ASSOC)) {
            /** @var array{date: string, count: int|string} $row */
            $flowCounts[$row['date']] = (int) $row['count'];
        }

        $scheduleCounts = [];
        $stmt = $this->client->prepare(
            'SELECT DATE(time) AS date, COUNT(*) AS count FROM schedule_exception_list' . $where .
            ' GROUP BY DATE(time) ORDER BY date ASC'
        );
        $stmt->execute($params);
        while ($row = $stmt->fetch(Client::FETCH_ASSOC)) {
            /** @var array{date: string, count: int|string} $row */
            $scheduleCounts[$row['date']] = (int) $row['count'];
        }

        $dates = array_unique(array_merge(array_keys($flowCounts), array_keys($scheduleCounts)));
        sort($dates);

        foreach ($dates as $date) {
            yield new ExceptionStatsEntity(
                date: $date,
                flow: $flowCounts[$date] ?? 0,
                schedule: $scheduleCounts[$date] ?? 0,
            );
        }
    }

    public function truncateFlowList(): void
    {
        $this->client->exec('DELETE FROM flow_list');
        $this->client->exec('DELETE FROM flow_run_list');
        $this->client->exec('DELETE FROM flow_exception_list');
        $this->client->exec('DELETE FROM schedule_exception_list');
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    /**
     * @param array<string, mixed> $params
     * @return array{string, array<string, mixed>}
     */
    private function applyStatusFilter(string $where, array $params, ?string $status): array
    {
        if ($status === null || $status === '') {
            return [$where, $params];
        }

        $values = $status === 'IN_PROGRESS'
            ? ['IN_PROGRESS', 'IN_PROGRESS_EXCEEDED']
            : array_map('trim', explode(',', $status));

        $placeholders = [];
        foreach ($values as $i => $value) {
            $key = ':status' . $i;
            $placeholders[] = $key;
            $params[$key] = $value;
        }

        $condition = 'status IN (' . implode(', ', $placeholders) . ')';

        $where = $where === '' ? ' WHERE ' . $condition : $where . ' AND ' . $condition;

        return [$where, $params];
    }

    /**
     * @return array{string, array<string, mixed>}
     */
    private function buildDateFilter(?DateTimeInterface $from, ?DateTimeInterface $to, string $searchColumn = 'flow_time'): array
    {
        $where = '';
        $params = [];

        if ($from instanceof DateTimeInterface && $to instanceof DateTimeInterface) {
            $where = ' WHERE ' . $searchColumn . ' BETWEEN :from AND :to';
            $params[':from'] = $from->format('Y-m-d H:i:s.u');
            $params[':to'] = $to->format('Y-m-d H:i:s.u');
        } elseif ($from instanceof DateTimeInterface) {
            $where = ' WHERE ' . $searchColumn . ' >= :from';
            $params[':from'] = $from->format('Y-m-d H:i:s.u');
        } elseif ($to instanceof DateTimeInterface) {
            $where = ' WHERE ' . $searchColumn . ' <= :to';
            $params[':to'] = $to->format('Y-m-d H:i:s.u');
        }

        return [$where, $params];
    }
}
