<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\MySqlClientTestTrait;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;
use Wundii\Flowcrafter\Storage\SortEnum;

final class FlowStorageMySqlTest extends TestCase
{
    use MySqlClientTestTrait;

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
    }
}
