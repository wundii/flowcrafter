<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\RedisClientTestTrait;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;
use Wundii\Flowcrafter\Storage\Redis;
use Wundii\Flowcrafter\Storage\SortEnum;

final class FlowStorageRedisTest extends TestCase
{
    use RedisClientTestTrait;

    /**
     * @throws Exception
     */
    public function testFindFlowsASC(): void
    {
        $redis = new Redis(
            $this->container->getHost(),
            $this->container->getMappedPort(6379),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $redis,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($redis->findAllFlows(SortEnum::ASC));
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
        $redis = new Redis(
            $this->container->getHost(),
            $this->container->getMappedPort(6379),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $redis,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($redis->findAllFlows());
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
        $redis = new Redis(
            $this->container->getHost(),
            $this->container->getMappedPort(6379),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $redis,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($redis->findFlowsBySource(WorkflowMock::class, SortEnum::ASC));
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
        $redis = new Redis(
            $this->container->getHost(),
            $this->container->getMappedPort(6379),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $redis,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($redis->findFlowsBySource(WorkflowMock::class));
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowEntity::class, $flows[1]);
        $this->assertLessThan($flows[0]->flowHash, $flows[1]->flowHash);
    }

    public function testFindFlowByHash(): void
    {
        $redis = new Redis(
            $this->container->getHost(),
            $this->container->getMappedPort(6379),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $redis,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $flow = $redis->findFlowByHash($flow->getHash());

        $this->assertInstanceOf(Flow::class, $flow);
    }

    public function testFindFlowByRuntimeHash(): void
    {
        $redis = new Redis(
            $this->container->getHost(),
            $this->container->getMappedPort(6379),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $redis,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);
        $flow = $redis->findFlowByRuntimeHash($flow->getRuntimeHash());

        $this->assertInstanceOf(Flow::class, $flow);
    }
}
