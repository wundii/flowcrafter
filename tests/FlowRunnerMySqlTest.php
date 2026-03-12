<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\FailStubMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\MySqlClientTestTrait;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\MySql;

final class FlowRunnerMySqlTest extends TestCase
{
    use MySqlClientTestTrait;

    public function testRunReturnsMessageReturnInterface(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $this->storage(),
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $this->assertCount(6, $flowRunner->getFlow()->getFlowMessages());

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_SCHEMA);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_INSTANCE);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_RUN);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_MESSAGE);
        $this->assertCount(6, iterator_to_array($stmt->fetchAll()));
    }

    // public function testRunTryRewriteSchema(): void
    // {
    //     $storage = $this->storage();
    //
    //     $flowRunner = new FlowRunner(
    //         type: 'flow.workflow.v1',
    //         flowSource: WorkflowMock::class,
    //         storage: $storage,
    //     );
    //     $flowRunner->run(new MessageInitMock('test data'));
    //
    //     $this->expectException(InvalidArgumentException::class);
    //
    //     $flow = $flowRunner->getFlow();
    //     $storage->registerFlowSchema($flow->getSchema());
    // }

    public function testRunFail(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.fail.v1',
            flowSource: WorkflowFailMock::class,
            storage: $this->storage(),
        );

        try {
            $flowRunner->run(new MessageInitMock('test data'));
        } catch (Exception $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
        }

        $flow = $flowRunner->getFlow();
        $exceptions = $flow->getFlowExceptions();
        $this->assertCount(1, $exceptions);

        $exception = $exceptions[0];
        $this->assertInstanceOf(FlowException::class, $exception);
        $this->assertSame($flow->getHash(), $exception->getFlowHash());
        $this->assertSame($flow->getRuntimeHash(), $exception->getFlowRuntimeHash());
        $this->assertSame(FailStubMock::class, $exception->getStubSource());
        $this->assertStringStartsWith('Test Exception', $exception->getMessage());
    }
}
