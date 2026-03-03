<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\EsdbClientTestTrait;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;
use Wundii\Flowcrafter\Storage\Esdb;
use Wundii\Flowcrafter\Storage\SortEnum;

final class FlowStorageEsdbTest extends TestCase
{
    use EsdbClientTestTrait;

    /**
     * @throws Exception
     */
    public function testFindFlowsASC(): void
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
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($esdb->findAllFlows(SortEnum::ASC));
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
        $esdb = new Esdb(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $esdb,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($esdb->findAllFlows());
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
        $esdb = new Esdb(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $esdb,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($esdb->findFlowsBySource(WorkflowMock::class, SortEnum::ASC));
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
        $esdb = new Esdb(
            $this->container->getBaseUrl(),
            $this->container->getApiToken(),
        );

        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $esdb,
        );
        $flowRunner->run(new MessageInitMock('test data1'));
        $flowRunner->run(new MessageInitMock('test data2'));

        $flows = iterator_to_array($esdb->findFlowsBySource(WorkflowMock::class));
        $this->assertCount(2, $flows);
        $this->assertInstanceOf(FlowEntity::class, $flows[0]);
        $this->assertInstanceOf(FlowEntity::class, $flows[1]);
        $this->assertLessThan($flows[0]->flowHash, $flows[1]->flowHash);
    }

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
        $this->assertInstanceOf(Flow::class, $flow);

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
        $this->assertInstanceOf(Flow::class, $flow);

        $flow = $esdb->findFlowByRuntimeHash($flow->getRuntimeHash());
        $this->assertInstanceOf(Flow::class, $flow);
    }
}
