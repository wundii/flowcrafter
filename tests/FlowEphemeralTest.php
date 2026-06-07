<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\NullStorageMock;
use Tests\MockClass\SqliteStorageMock;
use Tests\MockClass\WorkflowEphemeralMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Attribute\FlowEphemeral;
use Wundii\Flowcrafter\DependencyInjection\DependencyRegistry;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\FlowScheduler;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;

final class FlowEphemeralTest extends TestCase
{
    public function testFlowEphemeralAttributeDefaultExpiryDays(): void
    {
        $flowEphemeral = new FlowEphemeral();
        $this->assertSame(14, $flowEphemeral->expiryDays);
    }

    public function testFlowEphemeralAttributeCustomExpiryDays(): void
    {
        $flowEphemeral = new FlowEphemeral(expiryDays: 30);
        $this->assertSame(30, $flowEphemeral->expiryDays);
    }

    public function testFlowEphemeralAttributeThrowsOnZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FlowEphemeral expiryDays must be at least 1.');
        new FlowEphemeral(expiryDays: 0);
    }

    public function testFlowEphemeralAttributeThrowsOnNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FlowEphemeral expiryDays must be at least 1.');
        new FlowEphemeral(expiryDays: -5);
    }

    public function testEphemeralFlowSkipsPrimaryStorageWrites(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        $storage->expects($this->once())->method('registerFlowSchema');
        $storage->expects($this->never())->method('registerFlowInstance');
        $storage->expects($this->never())->method('appendFlowRun');
        $storage->expects($this->atLeastOnce())->method('registerMessageSource');
        $storage->expects($this->atLeastOnce())->method('registerStepSource');
        $storage->expects($this->never())->method('appendFlowMessage');
        $storage->expects($this->never())->method('appendFlowResult');

        $storage->expects($this->once())
            ->method('appendFlow')
            ->with(
                $this->isInstanceOf(Flow::class),
                true,
                7,
            );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.ephemeral.v1',
            flowSource: WorkflowEphemeralMock::class,
            storage: $storage,
        );

        $result = $flowRunner->run(new MessageInitMock('ephemeral test'));

        $this->assertInstanceOf(MessageReturnInterface::class, $result);
    }

    public function testNonEphemeralFlowCallsPrimaryStorage(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        $storage->expects($this->once())->method('registerFlowSchema');
        $storage->expects($this->once())->method('registerFlowInstance');
        $storage->expects($this->once())->method('appendFlowRun');
        $storage->expects($this->atLeastOnce())->method('registerMessageSource');
        $storage->expects($this->atLeastOnce())->method('registerStepSource');
        $storage->expects($this->atLeastOnce())->method('appendFlowMessage');

        $storage->expects($this->once())
            ->method('appendFlow')
            ->with(
                $this->isInstanceOf(Flow::class),
                false,
                0,
            );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );

        $result = $flowRunner->run(new MessageInitMock('normal test'));

        $this->assertInstanceOf(MessageReturnInterface::class, $result);
    }

    public function testEphemeralFlowRunsSuccessfully(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.ephemeral.v1',
            flowSource: WorkflowEphemeralMock::class,
        );

        $result = $flowRunner->run(new MessageInitMock('ephemeral no storage'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
    }

    public function testEphemeralFlowWithNullStorageMock(): void
    {
        $nullStorageMock = new NullStorageMock();

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.ephemeral.v1',
            flowSource: WorkflowEphemeralMock::class,
            storage: $nullStorageMock,
        );

        $result = $flowRunner->run(new MessageInitMock('ephemeral disabled'));

        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame(0, $nullStorageMock->countFlows());
    }

    public function testServiceEphemeralWriteAndReadByHash(): void
    {
        $sqliteFile = sys_get_temp_dir() . '/flowcrafter_test_ephemeral_' . uniqid() . '.sqlite';

        try {
            $sqliteStorageMock = new SqliteStorageMock($sqliteFile);
            $sqliteStorageMock->initializeDatabase();

            $flowRunner = new FlowRunner(
                type: 'flow.workflow.ephemeral.v1',
                flowSource: WorkflowEphemeralMock::class,
                storage: $sqliteStorageMock,
            );

            $result = $flowRunner->run(new MessageInitMock('ephemeral data'));
            $this->assertInstanceOf(MessageReturnInterface::class, $result);

            $flow = $flowRunner->getFlow();
            $this->assertInstanceOf(Flow::class, $flow);

            $json = $sqliteStorageMock->findEphemeralFlowJson($flow->getHash());
            $this->assertNotNull($json);

            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('flowHash', $decoded);
            $this->assertSame($flow->getHash(), $decoded['flowHash']);
        } finally {
            if (file_exists($sqliteFile)) {
                unlink($sqliteFile);
            }
        }
    }

    public function testServiceEphemeralReadByRuntimeHash(): void
    {
        $sqliteFile = sys_get_temp_dir() . '/flowcrafter_test_ephemeral_' . uniqid() . '.sqlite';

        try {
            $sqliteStorageMock = new SqliteStorageMock($sqliteFile);
            $sqliteStorageMock->initializeDatabase();

            $flowRunner = new FlowRunner(
                type: 'flow.workflow.ephemeral.v1',
                flowSource: WorkflowEphemeralMock::class,
                storage: $sqliteStorageMock,
            );

            $flowRunner->run(new MessageInitMock('ephemeral runtime'));

            $flow = $flowRunner->getFlow();
            $this->assertInstanceOf(Flow::class, $flow);

            $json = $sqliteStorageMock->findEphemeralFlowJsonByRuntimeHash($flow->getRuntimeHash());
            $this->assertNotNull($json);

            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded);
            $this->assertSame($flow->getHash(), $decoded['flowHash']);
        } finally {
            if (file_exists($sqliteFile)) {
                unlink($sqliteFile);
            }
        }
    }

    public function testServiceEphemeralReturnsNullForUnknownHash(): void
    {
        $sqliteFile = sys_get_temp_dir() . '/flowcrafter_test_ephemeral_' . uniqid() . '.sqlite';

        try {
            $sqliteStorageMock = new SqliteStorageMock($sqliteFile);
            $sqliteStorageMock->initializeDatabase();

            $this->assertNull($sqliteStorageMock->findEphemeralFlowJson('nonexistent-hash'));
            $this->assertNull($sqliteStorageMock->findEphemeralFlowJsonByRuntimeHash('nonexistent-runtime'));
        } finally {
            if (file_exists($sqliteFile)) {
                unlink($sqliteFile);
            }
        }
    }

    public function testServiceCleanupEphemeralRemovesExpiredEntries(): void
    {
        $sqliteFile = sys_get_temp_dir() . '/flowcrafter_test_ephemeral_' . uniqid() . '.sqlite';

        try {
            $sqliteStorageMock = new SqliteStorageMock($sqliteFile);
            $sqliteStorageMock->initializeDatabase();

            $flowRunner = new FlowRunner(
                type: 'flow.workflow.ephemeral.v1',
                flowSource: WorkflowEphemeralMock::class,
                storage: $sqliteStorageMock,
            );

            $flowRunner->run(new MessageInitMock('ephemeral cleanup'));

            $flow = $flowRunner->getFlow();
            $this->assertInstanceOf(Flow::class, $flow);

            $this->assertNotNull($sqliteStorageMock->findEphemeralFlowJson($flow->getHash()));

            $pdo = new \PDO('sqlite:' . $sqliteFile);
            $pdo->exec(sprintf(
                "UPDATE flow_ephemeral_list SET expires_at = '2000-01-01 00:00:00.000000' WHERE flow_hash = '%s'",
                $flow->getHash(),
            ));

            $this->assertNull($sqliteStorageMock->findEphemeralFlowJson($flow->getHash()));

            $sqliteStorageMock->cleanupEphemeral();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM flow_ephemeral_list WHERE flow_hash = ?');
            $stmt->execute([$flow->getHash()]);
            $this->assertSame(0, (int) $stmt->fetchColumn());

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM flow_list WHERE flow_hash = ?');
            $stmt->execute([$flow->getHash()]);
            $this->assertSame(0, (int) $stmt->fetchColumn());

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM flow_run_list WHERE flow_hash = ?');
            $stmt->execute([$flow->getHash()]);
            $this->assertSame(0, (int) $stmt->fetchColumn());
        } finally {
            if (file_exists($sqliteFile)) {
                unlink($sqliteFile);
            }
        }
    }

    public function testServiceCleanupEphemeralKeepsNonExpired(): void
    {
        $sqliteFile = sys_get_temp_dir() . '/flowcrafter_test_ephemeral_' . uniqid() . '.sqlite';

        try {
            $sqliteStorageMock = new SqliteStorageMock($sqliteFile);
            $sqliteStorageMock->initializeDatabase();

            $flowRunner = new FlowRunner(
                type: 'flow.workflow.ephemeral.v1',
                flowSource: WorkflowEphemeralMock::class,
                storage: $sqliteStorageMock,
            );

            $flowRunner->run(new MessageInitMock('ephemeral keep'));

            $flow = $flowRunner->getFlow();
            $this->assertInstanceOf(Flow::class, $flow);

            $sqliteStorageMock->cleanupEphemeral();

            $this->assertNotNull($sqliteStorageMock->findEphemeralFlowJson($flow->getHash()));
        } finally {
            if (file_exists($sqliteFile)) {
                unlink($sqliteFile);
            }
        }
    }

    public function testSchedulerTickCallsCleanupEphemeral(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        $storage->expects($this->once())
            ->method('cleanupEphemeral');

        $queue = $this->createStub(QueueInterface::class);

        $flowScheduler = new FlowScheduler($storage, $queue, new DependencyRegistry(), []);
        $flowScheduler->tick();
    }

    public function testNonEphemeralFlowDoesNotWriteToEphemeralTable(): void
    {
        $sqliteFile = sys_get_temp_dir() . '/flowcrafter_test_ephemeral_' . uniqid() . '.sqlite';

        try {
            $sqliteStorageMock = new SqliteStorageMock($sqliteFile);
            $sqliteStorageMock->initializeDatabase();

            $flowRunner = new FlowRunner(
                type: 'flow.workflow.v1',
                flowSource: WorkflowMock::class,
                storage: $sqliteStorageMock,
            );

            $flowRunner->run(new MessageInitMock('normal flow'));

            $flow = $flowRunner->getFlow();
            $this->assertInstanceOf(Flow::class, $flow);

            $this->assertNull($sqliteStorageMock->findEphemeralFlowJson($flow->getHash()));

            $pdo = new \PDO('sqlite:' . $sqliteFile);
            $stmt = $pdo->query('SELECT COUNT(*) FROM flow_ephemeral_list');
            $this->assertSame(0, (int) $stmt->fetchColumn());
        } finally {
            if (file_exists($sqliteFile)) {
                unlink($sqliteFile);
            }
        }
    }
}
