<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\EsdbClientTestTrait;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\Esdb;

final class FlowStorageEsdbTest extends TestCase
{
    use EsdbClientTestTrait;

    public function testFindFlowByHash(): void
    {
        $esdb = new Esdb(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $esdb,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(\Wundii\Flowcrafter\Flow::class, $flow);
        $flow = $esdb->findFlowByHash($flow->getHash());

        $this->assertInstanceOf(Flow::class, $flow);
    }

    public function testFindFlowByRuntimeHash(): void
    {
        $esdb = new Esdb(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $esdb,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(\Wundii\Flowcrafter\Flow::class, $flow);
        $flow = $esdb->findFlowByRuntimeHash($flow->getRuntimeHash());

        $this->assertInstanceOf(Flow::class, $flow);
    }
}
