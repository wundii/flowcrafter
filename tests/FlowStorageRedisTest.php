<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowEmptyMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\RedisClientTestTrait;
use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Source;
use Wundii\Flowcrafter\Storage\Entity\ExceptionListEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowListEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowStatsEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowTypeStatsEntity;
use Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity;
use Wundii\Flowcrafter\Storage\Entity\StepSourceEntity;

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
        $this->assertInstanceOf(FlowListEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowListEntity::class, $flows[1]);
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
        $this->assertInstanceOf(FlowListEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowListEntity::class, $flows[1]);
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
        $this->assertInstanceOf(FlowListEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowListEntity::class, $flows[1]);
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
        $this->assertInstanceOf(FlowListEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowListEntity::class, $flows[1]);
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

        $exceptions = [...$storage->findAllExceptions(SortEnum::ASC)];
        $this->assertCount(2, $exceptions);
        $this->assertSame(2, $storage->countExceptions());
        $this->assertInstanceOf(ExceptionListEntity::class, $exceptions[0]);
        $this->assertInstanceOf(ExceptionListEntity::class, $exceptions[1]);
        $this->assertGreaterThan($exceptions[0]->hash, $exceptions[1]->hash);
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

        $exceptions = [...$storage->findAllExceptions()];
        $this->assertCount(2, $exceptions);
        $this->assertSame(2, $storage->countExceptions());
        $this->assertInstanceOf(ExceptionListEntity::class, $exceptions[0]);
        $this->assertInstanceOf(ExceptionListEntity::class, $exceptions[1]);
        $this->assertLessThan($exceptions[0]->hash, $exceptions[1]->hash);
    }

    public function testFindFlowInstanceByHash(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: 'test-subject',
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);

        $instance = $storage->findFlowInstanceByHash($flow->getHash());
        $this->assertInstanceOf(FlowInstanceEntity::class, $instance);
        $this->assertSame($flow->getHash(), $instance->flowHash);
        $this->assertSame('flow.workflow.v1', $instance->flowType);
        $this->assertSame(WorkflowMock::class, $instance->flowSource);
        $this->assertSame('test-subject', $instance->flowSubject);
        $this->assertSame($flow->getSchemaHash(), $instance->flowSchemaHash);
        $this->assertNotNull($instance->time);

        $this->assertNotInstanceOf(\Wundii\Flowcrafter\Storage\Entity\FlowInstanceEntity::class, $storage->findFlowInstanceByHash('nonexistent-hash'));
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
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertCount(1, $flow->runs());
    }

    public function testFindFlowByHashWithEmptyInitMessage(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.empty.v1',
            flowSource: WorkflowEmptyMock::class,
            storage: $storage,
        );
        $flowRunner->run(new EmptyInitMessage());

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $flow = $storage->findFlowByHash($flow->getHash());

        $this->assertInstanceOf(Flow::class, $flow);
        $this->assertCount(1, $flow->getFlowMessages());
        $this->assertCount(0, $flow->getFlowExceptions());
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertCount(1, $flow->runs());
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
        $this->assertCount(1, $flow->getFlowResults());
        $this->assertCount(1, $flow->runs());
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
        $this->assertInstanceOf(FlowListEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowListEntity::class, $flows[1]);
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
        $this->assertInstanceOf(FlowListEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowListEntity::class, $flows[1]);
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
    public function testFindFlowsByTypeASC(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($storage->findFlowsByType('flow.workflow', SortEnum::ASC));
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowListEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowListEntity::class, $flows[1]);
        $this->assertGreaterThan($flows[0]->flowHash, $flows[1]->flowHash);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowsByTypeDESC(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($storage->findFlowsByType('flow.workflow'));
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowListEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowListEntity::class, $flows[1]);
        $this->assertLessThan($flows[0]->flowHash, $flows[1]->flowHash);
    }

    /**
     * @throws Exception
     */
    public function testCountFlowsByType(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $this->assertSame(2, $storage->countFlowsByType('flow.workflow'));
        $this->assertSame(0, $storage->countFlowsByType('flow.nonexistent'));
    }

    /**
     * @throws Exception
     */
    public function testFindFlowsByTypeWithFromToMatching(): void
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

        $flows = iterator_to_array($storage->findFlowsByType('flow.workflow', SortEnum::DESC, 1000, 0, $from, $to));
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowListEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowListEntity::class, $flows[1]);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowsByTypeWithFromToOutOfRange(): void
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

        $flows = iterator_to_array($storage->findFlowsByType('flow.workflow', SortEnum::DESC, 1000, 0, $from, $to));
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

        $exceptions = [...$storage->findAllExceptions(SortEnum::DESC, 1000, 0, $from, $to)];
        $this->assertCount(2, $exceptions);
        $this->assertInstanceOf(ExceptionListEntity::class, $exceptions[0]);
        $this->assertInstanceOf(ExceptionListEntity::class, $exceptions[1]);
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

        $exceptions = [...$storage->findAllExceptions(SortEnum::DESC, 1000, 0, $from, $to)];
        $this->assertCount(0, $exceptions);
    }

    /**
     * @throws Exception
     */
    public function testCountFlowsBySubject(): void
    {
        $storage = $this->storage();
        $flowRunnerA = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: 'order-12345',
            storage: $storage,
        );
        $flowRunnerB = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: 'order-67890',
            storage: $storage,
        );
        $flowRunnerC = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: 'user-99999',
            storage: $storage,
        );
        $flowRunnerA->run(new MessageInitMock('test data1'));
        $flowRunnerB->run(new MessageInitMock('test data2'));
        $flowRunnerC->run(new MessageInitMock('test data3'));

        $this->assertSame(2, $storage->countFlowsBySubject('order'));
        $this->assertSame(1, $storage->countFlowsBySubject('12345'));
        $this->assertSame(1, $storage->countFlowsBySubject('user'));
        $this->assertSame(0, $storage->countFlowsBySubject('nonexistent'));
    }

    /**
     * @throws Exception
     */
    public function testFindFlowsBySubject(): void
    {
        $storage = $this->storage();
        $flowRunnerA = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: 'order-12345',
            storage: $storage,
        );
        $flowRunnerB = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: 'order-67890',
            storage: $storage,
        );
        $flowRunnerC = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: 'user-99999',
            storage: $storage,
        );
        $flowRunnerA->run(new MessageInitMock('test data1'));
        $flowRunnerB->run(new MessageInitMock('test data2'));
        $flowRunnerC->run(new MessageInitMock('test data3'));

        $flows = iterator_to_array($storage->findFlowsBySubject('order'));
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowListEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowListEntity::class, $flows[1]);

        $flows = iterator_to_array($storage->findFlowsBySubject('12345'));
        $this->assertCount(1, $flows);
        $this->assertSame('order-12345', $flows[0]->flowSubject);

        $flows = iterator_to_array($storage->findFlowsBySubject('nonexistent'));
        $this->assertCount(0, $flows);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowsBySubjectWithFromToMatching(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: 'order-12345',
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));

        $from = new DateTimeImmutable('-1 day');
        $to = new DateTimeImmutable('+1 day');

        $flows = iterator_to_array($storage->findFlowsBySubject('order', SortEnum::DESC, 1000, 0, $from, $to));
        $this->assertCount(1, $flows);
        $this->assertInstanceOf(FlowListEntity::class, $flows[0]);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowsBySubjectWithFromToOutOfRange(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            flowSubject: 'order-12345',
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));

        $from = new DateTimeImmutable('2020-01-01');
        $to = new DateTimeImmutable('2020-01-02');

        $flows = iterator_to_array($storage->findFlowsBySubject('order', SortEnum::DESC, 1000, 0, $from, $to));
        $this->assertCount(0, $flows);
    }

    /**
     * @throws Exception
     */
    public function testFindStepSource(): void
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
        $flowMessage = $flow->getFlowMessages()[0];

        $stepSource = $storage->findStepSourceByHash($flowMessage->getStepHash());
        $this->assertInstanceOf(StepSourceEntity::class, $stepSource);
        $this->assertSame($flowMessage->getStepHash(), $stepSource->stepHash);
        $this->assertSame($flowMessage->getStepSource(), $stepSource->stepSource);
        $this->assertNotEmpty($stepSource->sourceContent);

        $stepSources = $storage->findStepSourcesByStepSource($flowMessage->getStepSource());
        $stepSources = iterator_to_array($stepSources);
        $this->assertCount(1, $stepSources);
        $this->assertInstanceOf(StepSourceEntity::class, $stepSources[0]);
        $this->assertSame($flowMessage->getStepHash(), $stepSources[0]->stepHash);
        $this->assertSame($flowMessage->getStepSource(), $stepSources[0]->stepSource);
        $this->assertNotEmpty($stepSources[0]->sourceContent);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowStats(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $stats = iterator_to_array($storage->findFlowStats());
        $this->assertCount(1, $stats);
        $this->assertInstanceOf(FlowStatsEntity::class, $stats[0]);
        $this->assertSame(date('Y-m-d'), $stats[0]->date);
        $this->assertSame(2, $stats[0]->instances);
        $this->assertSame(2, $stats[0]->runs);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowStatsWithFromTo(): void
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

        $stats = iterator_to_array($storage->findFlowStats($from, $to));
        $this->assertCount(1, $stats);
        $this->assertSame(2, $stats[0]->instances);
        $this->assertSame(2, $stats[0]->runs);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowStatsOutOfRange(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunner->run(new MessageInitMock('test data1'));

        $from = new DateTimeImmutable('2020-01-01');
        $to = new DateTimeImmutable('2020-01-02');

        $stats = iterator_to_array($storage->findFlowStats($from, $to));
        $this->assertCount(0, $stats);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowStatsWithFlowType(): void
    {
        $storage = $this->storage();
        $flowRunnerA = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunnerB = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: $storage,
        );
        $flowRunnerA->run(new MessageInitMock('test data1'));
        $flowRunnerA->run(new MessageInitMock('test data2'));

        try {
            $flowRunnerB->run(new MessageInitMock('test data3'));
        } catch (Exception) {
        }

        $stats = iterator_to_array($storage->findFlowStats(null, null, 'flow.workflow'));
        $this->assertCount(1, $stats);
        $this->assertSame(2, $stats[0]->instances);
        $this->assertSame(2, $stats[0]->runs);

        $stats = iterator_to_array($storage->findFlowStats(null, null, 'flow.workflow.fail'));
        $this->assertCount(1, $stats);
        $this->assertSame(1, $stats[0]->instances);
        $this->assertSame(1, $stats[0]->runs);

        $stats = iterator_to_array($storage->findFlowStats(null, null, 'flow.nonexistent'));
        $this->assertCount(0, $stats);
    }

    /**
     * @throws Exception
     */
    public function testFindFlowTypeStats(): void
    {
        $storage = $this->storage();
        $flowRunnerA = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $flowRunnerB = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: $storage,
        );
        $flowRunnerA->run(new MessageInitMock('test data1'));
        $flowRunnerA->run(new MessageInitMock('test data2'));

        try {
            $flowRunnerB->run(new MessageInitMock('test data3'));
        } catch (Exception) {
        }

        $stats = iterator_to_array($storage->findFlowTypeStats());
        $this->assertCount(2, $stats);

        $statsMap = [];
        foreach ($stats as $stat) {
            $this->assertInstanceOf(FlowTypeStatsEntity::class, $stat);
            $statsMap[$stat->prefix] = $stat;
        }

        $this->assertArrayHasKey('flow.workflow', $statsMap);
        $this->assertSame('flow.workflow.v1', $statsMap['flow.workflow']->flowType);
        $this->assertSame(2, $statsMap['flow.workflow']->total);
        $this->assertInstanceOf(\DateTimeInterface::class, $statsMap['flow.workflow']->lastTime);

        $this->assertArrayHasKey('flow.workflow.fail', $statsMap);
        $this->assertSame(1, $statsMap['flow.workflow.fail']->total);
        $this->assertSame(1, $statsMap['flow.workflow.fail']->failed);
        $this->assertSame(0, $statsMap['flow.workflow.fail']->successRate);
    }

    /**
     * @throws Exception
     */
    public function testFindAllMessageSources(): void
    {
        $storage = $this->storage();
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );

        $flowRunner->run(new MessageInitMock('test data'));

        $messageSources = iterator_to_array($storage->findAllMessageSources());
        $this->assertCount(4, $messageSources);

        foreach ($messageSources as $messageSource) {
            $this->assertInstanceOf(MessageSourceEntity::class, $messageSource);
            $this->assertNotEmpty($messageSource->messageHash);
            $this->assertNotEmpty($messageSource->messageSource);
            $this->assertNotEmpty($messageSource->propertyNames);
            $this->assertInstanceOf(\DateTimeInterface::class, $messageSource->time);
        }

        $sourcesByClass = [];
        foreach ($messageSources as $messageSource) {
            $sourcesByClass[$messageSource->messageSource] = $messageSource;
        }

        $this->assertArrayHasKey(MessageInitMock::class, $sourcesByClass);
        $messageSourceEntity = Source::message(MessageInitMock::class);
        $this->assertSame($messageSourceEntity->messageHash, $sourcesByClass[MessageInitMock::class]->messageHash);
        $this->assertSame($messageSourceEntity->propertyNames, $sourcesByClass[MessageInitMock::class]->propertyNames);
    }
}
