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
use Tests\Trait\RedisClientTestTrait;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowRunner;

final class FlowRunnerRedisTest extends TestCase
{
    use RedisClientTestTrait;

    public function testRunReturnsMessageReturnInterface(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $this->storage(),
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $this->assertCount(6, $flowRunner->getFlow()->getFlowMessages());

        $events = $this->client->keys('flow:*');
        $this->assertCount(9, $events);

        $flowSchemaEvents = $this->client->keys('flow:schema:*');
        $this->assertCount(1, $flowSchemaEvents);

        $flowInstanceEvents = $this->client->keys('flow:instance:*');
        $this->assertCount(1, $flowInstanceEvents);

        $flowRunEvents = $this->client->keys('flow:run:*');
        $this->assertCount(1, $flowRunEvents);

        $flowMessageEvents = $this->client->keys('flow:message:*');
        $this->assertCount(6, $flowMessageEvents);
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
        $this->assertSame('Test Exception', $exception->getMessage());
    }
}
