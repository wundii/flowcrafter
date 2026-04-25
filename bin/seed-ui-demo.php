<?php

declare(strict_types=1);

/**
 * Demo seed for the SQLite service storage.
 * Generates 14 days of flow activity with sporadic errors and exceptions.
 *
 * Usage: php bin/seed-ui-demo.php [--reset]
 *   --reset  Clear all tables before seeding
 */

require getcwd() . '/vendor/autoload.php';

use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfigRequirer;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowResult;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity;
use Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;
use Wundii\Flowcrafter\Storage\Service;

/**
 * Concrete subclass that satisfies the StorageInterface contract.
 * Only initializeDatabase(), truncateFlowList(), and getServiceClient() are used by the seed.
 */
class SeedStorage extends Service
{
    public function __construct(string $file)
    {
        parent::__construct($file);
    }

    public function getPdo(): PDO
    {
        return $this->getServiceClient();
    }

    public function isPrimaryStorageInitialized(): bool { return true; }
    public function registerFlowSchema(FlowSchema $flowSchema): void {}
    public function registerMessageSource(MessageSourceEntity $messageSourceEntity): void {}
    public function registerStubSource(StubSourceEntity $stubSourceEntity): void {}
    public function registerFlowInstance(Flow $flow): void {}
    public function appendFlowRun(Flow $flow, ?string $queueId = null): void {}
    public function appendFlowMessage(FlowMessage $flowMessage): void {}
    public function appendFlowException(FlowException $flowException): void {}
    public function appendFlowResult(FlowResult $flowResult): void {}
    public function appendObserveItem(string $type, string $flowSource, ?string $flowHash, string $messageSource, ?array $message, array $includeStubs = [], ?string $flowSubject = null): void {}
    public function openQueues(): int { return 0; }
    public function observeQueue(float $maxExecutionTimeInSeconds = 0.0): iterable { return []; }
    public function findAllFlowHashes(): iterable { return []; }
    public function findAllQueues(SortEnum $sortEnum = SortEnum::DESC): iterable { return []; }
    public function findAllSchemas(): iterable { return []; }
    public function findAllMessageSources(): iterable { return []; }
    public function findFlowInstanceByHash(string $flowHash): ?FlowInstanceEntity { return null; }
    public function findFlowByHash(string $flowHash): ?Flow { return null; }
    public function findFlowByRuntimeHash(string $flowRuntimeHash): ?Flow { return null; }
    public function findStubSourceByHash(string $stubHash): ?StubSourceEntity { return null; }
    public function findStubSourcesByStubSource(string $stubSource): iterable { return []; }
    public function findMessageSourceByMessageSource(string $messageSource): iterable { return []; }
}

// ---------------------------------------------------------------------------
// Fake domain data
// ---------------------------------------------------------------------------

$flowDefs = [
    [
        'source' => 'App\\Flowcrafter\\Flow\\OrderProcessingFlow',
        'type' => 'flow.order-processing.v1',
        'stubs' => [
            'App\\Flowcrafter\\Stub\\ValidateOrderStub',
            'App\\Flowcrafter\\Stub\\ReserveStockStub',
            'App\\Flowcrafter\\Stub\\ChargeCreditCardStub',
            'App\\Flowcrafter\\Stub\\SendConfirmationEmailStub',
        ],
        'subjects' => ['order-1001', 'order-1002', 'order-1003', 'order-2001', 'order-2002'],
        'weight' => 5,
    ],
    [
        'source' => 'App\\Flowcrafter\\Flow\\UserRegistrationFlow',
        'type' => 'flow.user-registration.v2',
        'stubs' => [
            'App\\Flowcrafter\\Stub\\CreateUserStub',
            'App\\Flowcrafter\\Stub\\SendWelcomeEmailStub',
            'App\\Flowcrafter\\Stub\\AssignDefaultRoleStub',
        ],
        'subjects' => ['user-5001', 'user-5002', 'user-5003'],
        'weight' => 3,
    ],
    [
        'source' => 'App\\Flowcrafter\\Flow\\InvoiceGenerationFlow',
        'type' => 'flow.invoice-generation.v1',
        'stubs' => [
            'App\\Flowcrafter\\Stub\\FetchBillingDataStub',
            'App\\Flowcrafter\\Stub\\CalculateTaxStub',
            'App\\Flowcrafter\\Stub\\GeneratePdfStub',
            'App\\Flowcrafter\\Stub\\StoreInvoiceStub',
        ],
        'subjects' => ['invoice-9001', 'invoice-9002'],
        'weight' => 2,
    ],
    [
        'source' => 'App\\Flowcrafter\\Flow\\ReportExportFlow',
        'type' => 'flow.report-export.v3',
        'stubs' => [
            'App\\Flowcrafter\\Stub\\FetchReportDataStub',
            'App\\Flowcrafter\\Stub\\FormatCsvStub',
            'App\\Flowcrafter\\Stub\\UploadToS3Stub',
        ],
        'subjects' => ['weekly-sales', 'monthly-kpi', 'daily-summary'],
        'weight' => 2,
    ],
    [
        'source' => 'App\\Flowcrafter\\Flow\\InventorySyncFlow',
        'type' => 'flow.inventory-sync.v1',
        'stubs' => [
            'App\\Flowcrafter\\Stub\\FetchExternalInventoryStub',
            'App\\Flowcrafter\\Stub\\DiffInventoryStub',
            'App\\Flowcrafter\\Stub\\ApplyInventoryChangesStub',
        ],
        'subjects' => ['warehouse-a', 'warehouse-b'],
        'weight' => 3,
    ],
];

$schedules = [
    ['class' => 'App\\Flowcrafter\\Schedule\\DailyReportSchedule', 'name' => 'daily-report', 'expr' => '0 6 * * *'],
    ['class' => 'App\\Flowcrafter\\Schedule\\InventorySyncSchedule', 'name' => 'inventory-sync', 'expr' => '*/15 * * * *'],
    ['class' => 'App\\Flowcrafter\\Schedule\\InvoiceGenerationSchedule', 'name' => 'invoice-generation', 'expr' => '0 2 * * *'],
];

$exceptionMessages = [
    'Connection timeout after 30s',
    'Invalid argument: expected string, got null',
    'Database deadlock detected, retry #3 failed',
    'External API returned 503 Service Unavailable',
    'Memory limit exceeded (256M)',
    'Unexpected NULL value in required field "customerId"',
    'Class not found: App\\Service\\LegacyBillingService',
    'SMTP authentication failed',
    'PDF generation timed out',
    'S3 upload failed: AccessDenied',
];

$observerFlows = [
    ['flow' => 'App\\Flowcrafter\\Flow\\OrderProcessingFlow', 'message' => 'App\\Flowcrafter\\Message\\OrderInitMessage'],
    ['flow' => 'App\\Flowcrafter\\Flow\\InventorySyncFlow', 'message' => 'App\\Flowcrafter\\Message\\EmptyInitMessage'],
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function uuid7(DateTimeInterface $dt): string
{
    $ms = (int) ($dt->format('Uv'));
    $hex = sprintf('%012x', $ms);
    $rand = bin2hex(random_bytes(10));
    return sprintf(
        '%s-%s-7%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($rand, 0, 3),
        dechex(0x8 | (hexdec(substr($rand, 3, 1)) & 0x3)) . substr($rand, 4, 3),
        substr($rand, 8, 12)
    );
}

function randomTime(DateTimeImmutable $dayStart, DateTimeImmutable $dayEnd): DateTimeImmutable
{
    $start = (int) $dayStart->format('U');
    $end = (int) $dayEnd->format('U');
    return (new DateTimeImmutable())->setTimestamp(random_int($start, $end));
}

function weightedRandom(array $items): array
{
    $total = array_sum(array_column($items, 'weight'));
    $r = random_int(1, $total);
    $cumulative = 0;
    foreach ($items as $item) {
        $cumulative += $item['weight'];
        if ($r <= $cumulative) {
            return $item;
        }
    }
    return $items[array_key_last($items)];
}

// ---------------------------------------------------------------------------
// Storage init
// ---------------------------------------------------------------------------

$reset = in_array('--reset', $argv ?? [], true);

$flowcrafterConfig = new FlowcrafterConfig();
$configFile = getcwd() . DIRECTORY_SEPARATOR . BootstrapConfig::DEFAULT_CONFIG_FILE;
if (file_exists($configFile)) {
    $bootstrapConfigRequirer = new BootstrapConfigRequirer(new BootstrapConfig($configFile));
    $bootstrapConfigRequirer->loadConfigFile($flowcrafterConfig);
}

$dbFile = $flowcrafterConfig->getServerStorage() ?? throw new RuntimeException('No server storage configured. Set setServerStorage() in your flowcrafter.php.');

$storage = new SeedStorage($dbFile);
$storage->initializeDatabase();

if ($reset) {
    echo "Resetting tables...\n";
    $storage->truncateFlowList();
}

$pdo = $storage->getPdo();

// ---------------------------------------------------------------------------
// Prepared statements
// ---------------------------------------------------------------------------

$stmtFlow = $pdo->prepare(
    'INSERT OR IGNORE INTO flow_list (flow_hash, flow_type, flow_source, flow_subject, flow_time, last_term, status) ' .
    'VALUES (:flow_hash, :flow_type, :flow_source, :flow_subject, :flow_time, :last_term, :status)'
);

$stmtRun = $pdo->prepare(
    'INSERT OR IGNORE INTO flow_run_list (flow_runtime_hash, flow_hash, flow_type, flow_time) ' .
    'VALUES (:flow_runtime_hash, :flow_hash, :flow_type, :flow_time)'
);

$stmtExc = $pdo->prepare(
    'INSERT OR IGNORE INTO flow_exception_list (hash, flow_hash, flow_runtime_hash, flow_type, stub_source, stub_hash, code, message, file, line, trace_string, time) ' .
    'VALUES (:hash, :flow_hash, :flow_runtime_hash, :flow_type, :stub_source, :stub_hash, :code, :message, :file, :line, :trace_string, :time)'
);

$stmtSchedExc = $pdo->prepare(
    'INSERT OR IGNORE INTO schedule_exception_list (hash, schedule_class, schedule_name, schedule_expression, code, message, file, line, trace_string, time) ' .
    'VALUES (:hash, :schedule_class, :schedule_name, :schedule_expression, :code, :message, :file, :line, :trace_string, :time)'
);

$stmtObsExc = $pdo->prepare(
    'INSERT OR IGNORE INTO observer_exception_list (hash, flow_source, message_source, queue_id, code, message, file, line, trace_string, time) ' .
    'VALUES (:hash, :flow_source, :message_source, :queue_id, :code, :message, :file, :line, :trace_string, :time)'
);

// ---------------------------------------------------------------------------
// Seed
// ---------------------------------------------------------------------------

$totalFlows = 0;
$totalExceptions = 0;
$totalSchedExc = 0;
$totalObsExc = 0;

$now = new DateTimeImmutable();

for ($day = 13; $day >= 0; $day--) {
    $dayStart = $now->modify("-{$day} days")->setTime(0, 0, 0);
    $isToday = $day === 0;
    $dayEnd = $isToday ? $now : $dayStart->setTime(23, 59, 59);

    $flowCount = random_int(50, 65);

    for ($i = 0; $i < $flowCount; $i++) {
        $def = weightedRandom($flowDefs);
        $flowTime = randomTime($dayStart, $dayEnd);
        $flowHash = uuid7($flowTime);
        $subject = $def['subjects'][array_rand($def['subjects'])];

        $statusRoll = random_int(1, 100);
        if ($statusRoll <= 2) {
            $status = 'FAILED';
        } elseif ($statusRoll <= 6) {
            $status = 'WARNING';
        } else {
            $status = 'OK';
        }

        $runCount = random_int(1, $status === 'FAILED' ? 3 : 2);
        $lastRunTime = $flowTime;

        for ($r = 0; $r < $runCount; $r++) {
            $runTime = $r === 0 ? $flowTime : $lastRunTime->modify('+' . random_int(10, 300) . ' seconds');
            $runtimeHash = uuid7($runTime);
            $lastRunTime = $runTime;

            $stmtRun->execute([
                ':flow_runtime_hash' => $runtimeHash,
                ':flow_hash' => $flowHash,
                ':flow_type' => $def['type'],
                ':flow_time' => $runTime->format('Y-m-d H:i:s.u'),
            ]);

            $addException = ($status === 'FAILED' && $r === $runCount - 1)
                || ($status === 'WARNING' && random_int(1, 20) === 1);

            if ($addException) {
                $excStub = $def['stubs'][array_rand($def['stubs'])];
                $excMsg = $exceptionMessages[array_rand($exceptionMessages)];
                $excHash = uuid7($runTime->modify('+1 second'));

                $stmtExc->execute([
                    ':hash' => $excHash,
                    ':flow_hash' => $flowHash,
                    ':flow_runtime_hash' => $runtimeHash,
                    ':flow_type' => $def['type'],
                    ':stub_source' => $excStub,
                    ':stub_hash' => md5($excStub),
                    ':code' => random_int(0, 1) === 0 ? 0 : random_int(400, 503),
                    ':message' => $excMsg,
                    ':file' => '/var/www/flowcrafter/src/' . str_replace(['App\\Flowcrafter\\Stub\\', '\\'], ['Stub/', '/'], $excStub) . '.php',
                    ':line' => random_int(30, 120),
                    ':trace_string' => "#0 /var/www/flowcrafter/src/FlowRunner.php(88): {$excStub}->process()\n#1 /var/www/flowcrafter/src/FlowObserver.php(79): FlowRunner->run()\n{main}",
                    ':time' => $runTime->modify('+1 second')->format('Y-m-d H:i:s.u'),
                ]);
                $totalExceptions++;
            }
        }

        $stmtFlow->execute([
            ':flow_hash' => $flowHash,
            ':flow_type' => $def['type'],
            ':flow_source' => $def['source'],
            ':flow_subject' => $subject,
            ':flow_time' => $flowTime->format('Y-m-d H:i:s.u'),
            ':last_term' => $lastRunTime->format('Y-m-d H:i:s.u'),
            ':status' => $status,
        ]);
        $totalFlows++;
    }

    if (random_int(1, 20) <= 4) {
        $sched = $schedules[array_rand($schedules)];
        $excTime = randomTime($dayStart, $dayEnd);
        $excHash = uuid7($excTime);

        $stmtSchedExc->execute([
            ':hash' => $excHash,
            ':schedule_class' => $sched['class'],
            ':schedule_name' => $sched['name'],
            ':schedule_expression' => $sched['expr'],
            ':code' => 0,
            ':message' => $exceptionMessages[array_rand($exceptionMessages)],
            ':file' => '/var/www/flowcrafter/src/Schedule/' . basename(str_replace('\\', '/', $sched['class'])) . '.php',
            ':line' => random_int(20, 60),
            ':trace_string' => "#0 /var/www/flowcrafter/src/Console/FlowConsole.php(42): {$sched['class']}->process()\n{main}",
            ':time' => $excTime->format('Y-m-d H:i:s.u'),
        ]);
        $totalSchedExc++;
    }

    if (random_int(1, 20) <= 3) {
        $obs = $observerFlows[array_rand($observerFlows)];
        $excTime = randomTime($dayStart, $dayEnd);
        $excHash = uuid7($excTime);

        $stmtObsExc->execute([
            ':hash' => $excHash,
            ':flow_source' => $obs['flow'],
            ':message_source' => $obs['message'],
            ':queue_id' => uuid7($excTime->modify('-5 seconds')),
            ':code' => 0,
            ':message' => $exceptionMessages[array_rand($exceptionMessages)],
            ':file' => '/var/www/flowcrafter/src/FlowObserver.php',
            ':line' => 88,
            ':trace_string' => "#0 /var/www/flowcrafter/src/FlowObserver.php(88): FlowRunner->run()\n{main}",
            ':time' => $excTime->format('Y-m-d H:i:s.u'),
        ]);
        $totalObsExc++;
    }

    $dateLabel = $dayStart->format('Y-m-d');
    echo "  {$dateLabel}: {$flowCount} flows\n";
}

echo "\nDone.\n";
echo "  Flows:               {$totalFlows}\n";
echo "  Flow exceptions:     {$totalExceptions}\n";
echo "  Schedule exceptions: {$totalSchedExc}\n";
echo "  Observer exceptions: {$totalObsExc}\n";
