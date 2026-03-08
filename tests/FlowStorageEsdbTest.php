<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\EsdbClientTestTrait;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\Entity\FlowEntity;

final class FlowStorageEsdbTest extends TestCase
{
    use EsdbClientTestTrait;

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
