<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\RedisClientTestTrait;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\Redis;

final class FlowStorageRedisTest extends TestCase
{
    use RedisClientTestTrait;

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
        $this->assertInstanceOf(\Wundii\Flowcrafter\Flow::class, $flow);
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
        $this->assertInstanceOf(\Wundii\Flowcrafter\Flow::class, $flow);
        $flow = $redis->findFlowByRuntimeHash($flow->getRuntimeHash());

        $this->assertInstanceOf(Flow::class, $flow);
    }
}
