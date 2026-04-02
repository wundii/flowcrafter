<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Storage;

use DateTimeImmutable;
use DateTimeInterface;
use PDO as Client;
use Symfony\Component\Filesystem\Filesystem;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;
use Wundii\Flowcrafter\Storage\Entity\FlowListEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowStatsEntity;

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
            SQL
        );
    }

    public function countFlows(): int
    {
        $stmt = $this->client->query('SELECT COUNT(*) FROM flow_list');
        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    public function countFlowsBySource(string $flowSource): int
    {
        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_list WHERE flow_source = :flow_source');
        $stmt->execute([
            ':flow_source' => $flowSource,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countFlowsByType(string $flowType): int
    {
        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_list WHERE flow_type LIKE :flow_type');
        $stmt->execute([
            ':flow_type' => $flowType . '.v%',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countFlowsBySubject(string $flowSubject): int
    {
        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_list WHERE flow_subject LIKE :flow_subject');

        $stmt->execute([
            ':flow_subject' => '%' . $flowSubject . '%',
        ]);

        return (int) $stmt->fetchColumn();
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
     * @return FlowListEntity[]
     */
    public function findAllFlows(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        [$where, $params] = $this->buildDateFilter($from, $to);

        $sql = 'SELECT * FROM flow_list' . $where .
            ' ORDER BY last_term ' . $sortEnum->name .
            ' LIMIT :top OFFSET :skip';

        $params[':top'] = $top;
        $params[':skip'] = $skip;

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);

        return array_map($this->mapFlowListRow(...), $stmt->fetchAll(Client::FETCH_ASSOC));
    }

    /**
     * @return FlowListEntity[]
     */
    public function findFlowsBySource(string $flowSource, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        [$where, $params] = $this->buildDateFilter($from, $to);

        $where = $where === '' ? ' WHERE flow_source = :flow_source' : $where . ' AND flow_source = :flow_source';
        $params[':flow_source'] = $flowSource;

        $sql = 'SELECT * FROM flow_list' . $where .
            ' ORDER BY last_term ' . $sortEnum->name .
            ' LIMIT :top OFFSET :skip';

        $params[':top'] = $top;
        $params[':skip'] = $skip;

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);

        return array_map($this->mapFlowListRow(...), $stmt->fetchAll(Client::FETCH_ASSOC));
    }

    /**
     * @return FlowListEntity[]
     */
    public function findFlowsByType(string $flowType, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        [$where, $params] = $this->buildDateFilter($from, $to);

        $where = $where === '' ? ' WHERE flow_type LIKE :flow_type' : $where . ' AND flow_type LIKE :flow_type';
        $params[':flow_type'] = $flowType . '.v%';

        $sql = 'SELECT * FROM flow_list' . $where .
            ' ORDER BY last_term ' . $sortEnum->name .
            ' LIMIT :top OFFSET :skip';

        $params[':top'] = $top;
        $params[':skip'] = $skip;

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);

        return array_map($this->mapFlowListRow(...), $stmt->fetchAll(Client::FETCH_ASSOC));
    }

    /**
     * @return FlowListEntity[]
     */
    public function findFlowsBySubject(string $flowSubject, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        [$where, $params] = $this->buildDateFilter($from, $to);

        $where = $where === '' ? ' WHERE flow_subject LIKE :flow_subject' : $where . ' AND flow_subject LIKE :flow_subject';
        $params[':flow_subject'] = '%' . $flowSubject . '%';

        $sql = 'SELECT * FROM flow_list' . $where .
            ' ORDER BY last_term ' . $sortEnum->name .
            ' LIMIT :top OFFSET :skip';

        $params[':top'] = $top;
        $params[':skip'] = $skip;

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);

        return array_map($this->mapFlowListRow(...), $stmt->fetchAll(Client::FETCH_ASSOC));
    }

    public function saveFlow(Flow $flow): void
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

    public function countExceptions(?DateTimeInterface $from = null, ?DateTimeInterface $to = null): int
    {
        [$where, $params] = $this->buildDateFilter($from, $to, 'time');

        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_exception_list' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function countExceptionsByFlowHash(string $flowHash): int
    {
        $stmt = $this->client->prepare('SELECT COUNT(*) FROM flow_exception_list WHERE flow_hash = :flow_hash');
        $stmt->execute([
            ':flow_hash' => $flowHash,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return FlowException[]
     */
    public function findAllExceptions(SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        [$where, $params] = $this->buildDateFilter($from, $to, 'time');

        $sql = 'SELECT * FROM flow_exception_list' . $where .
            ' ORDER BY time ' . $sortEnum->name .
            ' LIMIT :top OFFSET :skip';

        $params[':top'] = max(1, $top);
        $params[':skip'] = max(0, $skip);

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);

        return array_map($this->mapFlowExceptionListRow(...), $stmt->fetchAll(Client::FETCH_ASSOC));
    }

    /**
     * @return FlowException[]
     */
    public function findExceptionsByFlowHash(string $flowHash, SortEnum $sortEnum = SortEnum::DESC, int $top = 1000, int $skip = 0, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): iterable
    {
        if ($flowHash === '' || $flowHash === '*') {
            yield from $this->findAllExceptions($sortEnum, $top, $skip, $from, $to);
            return;
        }

        [$where, $params] = $this->buildDateFilter($from, $to, 'time');

        $where = $where === '' ? ' WHERE flow_hash = :flow_hash' : $where . ' AND flow_hash = :flow_hash';
        $params[':flow_hash'] = $flowHash;

        $sql = 'SELECT * FROM flow_exception_list' . $where .
            ' ORDER BY time ' . $sortEnum->name .
            ' LIMIT :top OFFSET :skip';

        $params[':top'] = max(1, $top);
        $params[':skip'] = max(0, $skip);

        $stmt = $this->client->prepare($sql);
        $stmt->execute($params);

        yield from array_map($this->mapFlowExceptionListRow(...), $stmt->fetchAll(Client::FETCH_ASSOC));
    }

    public function truncateFlowList(): void
    {
        $this->client->exec('DELETE FROM flow_list');
        $this->client->exec('DELETE FROM flow_run_list');
        $this->client->exec('DELETE FROM flow_exception_list');
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

    /**
     * @param array<string, mixed> $row
     */
    private function mapFlowListRow(array $row): FlowListEntity
    {
        /** @var array{flow_hash: string, flow_type: string, flow_source: string, flow_subject: string|null, flow_time: string, last_term: string, status: string} $row */
        return new FlowListEntity(
            flowHash: $row['flow_hash'],
            flowType: $row['flow_type'],
            flowSource: $row['flow_source'],
            flowSubject: $row['flow_subject'],
            flowTime: new DateTimeImmutable($row['flow_time']),
            lastTerm: new DateTimeImmutable($row['last_term']),
            statusEnum: StatusEnum::fromName($row['status']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapFlowExceptionListRow(array $row): FlowException
    {
        /** @var array{hash: string, flow_hash: string, flow_runtime_hash: string, flow_type: string, stub_source: class-string<StubInterface>, stub_hash: string, code: int|string, message: string, file: string, line: int|string, trace_string: string, time: string} $row */
        return new FlowException(
            flowHash: $row['flow_hash'],
            flowRuntimeHash: $row['flow_runtime_hash'],
            flowType: $row['flow_type'],
            stubSource: $row['stub_source'],
            stubHash: $row['stub_hash'],
            code: (int) $row['code'],
            message: $row['message'],
            file: $row['file'],
            line: (int) $row['line'],
            traceString: $row['trace_string'],
            time: new DateTimeImmutable($row['time']),
            hash: $row['hash'],
        );
    }
}
