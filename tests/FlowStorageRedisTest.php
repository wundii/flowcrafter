<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\RedisClientTestTrait;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;

final class FlowStorageRedisTest extends TestCase
{
    use RedisClientTestTrait;

    /**
     * @throws Exception
     */
    public function testFindFlowsASC(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($storage->findAllFlows(SortEnum::ASC));
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowEntity::class, $flows[1]);
        $this->assertGreaterThan($flows[0]->flowHash, $flows[1]->flowHash);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowsDESC(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($storage->findAllFlows());
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowEntity::class, $flows[1]);
        $this->assertLessThan($flows[0]->flowHash, $flows[1]->flowHash);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowsBySourceASC(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($storage->findFlowsBySource(WorkflowMock::class, SortEnum::ASC));
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowEntity::class, $flows[1]);
        $this->assertGreaterThan($flows[0]->flowHash, $flows[1]->flowHash);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowsBySourceDESC(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($storage->findFlowsBySource(WorkflowMock::class));
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowEntity::class, $flows[1]);
        $this->assertLessThan($flows[0]->flowHash, $flows[1]->flowHash);
    }

    /**
     * @throws Exception
     */
    public function testFindExceptionsASC(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: $storage,
        );

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        $exceptions = iterator_to_array($storage->findAllExceptions(SortEnum::ASC));
        $this->assertCount(2, $exceptions);
        $this->assertSame(2, $storage->countExceptions());
        $this->assertInstanceOf(FlowException::class, $exceptions[0]);
        $this->assertInstanceOf(FlowException::class, $exceptions[1]);
        $this->assertGreaterThan($exceptions[0]->getHash(), $exceptions[1]->getHash());
    }

    /**
     * @throws Exception
     */
    public function testFindExceptionsDESC(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: $storage,
        );

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        $exceptions = iterator_to_array($storage->findAllExceptions());
        $this->assertCount(2, $exceptions);
        $this->assertSame(2, $storage->countExceptions());
        $this->assertInstanceOf(FlowException::class, $exceptions[0]);
        $this->assertInstanceOf(FlowException::class, $exceptions[1]);
        $this->assertLessThan($exceptions[0]->getHash(), $exceptions[1]->getHash());
    }

    public function testFindFlowByHash(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $flow = $storage->findFlowByHash($flow->getHash());

        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(1, $flow->getFlowRuns());
    }

    public function testFindFlowByRuntimeHash(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $flow = $storage->findFlowByRuntimeHash($flow->getRuntimeHash());

        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(6, $flow->getFlowMessages());
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(1, $flow->getFlowRuns());
    }

    /**
     * @throws Exception
     */
    public function testFindAllFlowsWithFromToMatching(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $from = new DateTimeImmutable('-1 day');
        $to = new DateTimeImmutable('+1 day');

        $flows = iterator_to_array($storage->findAllFlows(SortEnum::DESC, 1000, 0, $from, $to));
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowEntity::class, $flows[1]);
    }

    /**
     * @throws Exception
     */
    public function testFindAllFlowsWithFromToOutOfRange(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $from = new DateTimeImmutable('2020-01-01');
        $to = new DateTimeImmutable('2020-01-02');

        $flows = iterator_to_array($storage->findAllFlows(SortEnum::DESC, 1000, 0, $from, $to));
        $this->assertCount(0, $flows);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowsBySourceWithFromToMatching(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $from = new DateTimeImmutable('-1 day');
        $to = new DateTimeImmutable('+1 day');

        $flows = iterator_to_array($storage->findFlowsBySource(WorkflowMock::class, SortEnum::DESC, 1000, 0, $from, $to));
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowEntity::class, $flows[1]);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowsBySourceWithFromToOutOfRange(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $from = new DateTimeImmutable('2020-01-01');
        $to = new DateTimeImmutable('2020-01-02');

        $flows = iterator_to_array($storage->findFlowsBySource(WorkflowMock::class, SortEnum::DESC, 1000, 0, $from, $to));
        $this->assertCount(0, $flows);
    }

    /**
     * @throws Exception
     */
    public function testFindAllExceptionsWithFromToMatching(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: $storage,
        );

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        $from = new DateTimeImmutable('-1 day');
        $to = new DateTimeImmutable('+1 day');

        $exceptions = iterator_to_array($storage->findAllExceptions(SortEnum::DESC, 1000, 0, $from, $to));
        $this->assertCount(2, $exceptions);
        $this->assertInstanceOf(FlowException::class, $exceptions[0]);
        $this->assertInstanceOf(FlowException::class, $exceptions[1]);
    }

    /**
     * @throws Exception
     */
    public function testFindAllExceptionsWithFromToOutOfRange(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: $storage,
        );

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        $from = new DateTimeImmutable('2020-01-01');
        $to = new DateTimeImmutable('2020-01-02');

        $exceptions = iterator_to_array($storage->findAllExceptions(SortEnum::DESC, 1000, 0, $from, $to));
        $this->assertCount(0, $exceptions);
    }

    /**
     * @throws Exception
     */
    public function testFindExceptionsByFlowHashWithFromToMatching(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: $storage,
        );

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);

        $from = new DateTimeImmutable('-1 day');
        $to = new DateTimeImmutable('+1 day');

        $exceptions = iterator_to_array($storage->findExceptionsByFlowHash($flow->getHash(), SortEnum::DESC, 1000, 0, $from, $to));
        $this->assertCount(1, $exceptions);
        $this->assertInstanceOf(FlowException::class, $exceptions[0]);
    }

    /**
     * @throws Exception
     */
    public function testFindExceptionsByFlowHashWithFromToOutOfRange(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: $storage,
        );

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);

        $from = new DateTimeImmutable('2020-01-01');
        $to = new DateTimeImmutable('2020-01-02');

        $exceptions = iterator_to_array($storage->findExceptionsByFlowHash($flow->getHash(), SortEnum::DESC, 1000, 0, $from, $to));
        $this->assertCount(0, $exceptions);
    }
}
