<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\FailStepMock;
use Tests\MockClass\MessageDataMock;
use Tests\MockClass\MessageDataSecondMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageSubDataMock;
use Tests\MockClass\PostStepMock;
use Tests\MockClass\WorkflowEmptyMock;
use Tests\MockClass\WorkflowFailMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\RedisClientTestTrait;
use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

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
        $this->assertCount(18, $events);

        $flowSchemaEvents = $this->client->keys('flow:schema:*');
        $this->assertCount(1, $flowSchemaEvents);

        $flowInstanceEvents = $this->client->keys('flow:instance:*');
        $this->assertCount(1, $flowInstanceEvents);

        $flowRunEvents = $this->client->keys('flow:run:*');
        $this->assertCount(1, $flowRunEvents);

        $flowMessageEvents = $this->client->keys('flow:message:*');
        $this->assertCount(6, $flowMessageEvents);

        $flowResultEvents = $this->client->keys('flow:result:*');
        $this->assertCount(1, $flowResultEvents);

        $flowMessageEvents = $this->client->keys('flow:source:step:*');
        $this->assertCount(4, $flowMessageEvents);

        $flowSourceMessageKeys = $this->client->keys('flow:source:message:*');
        $this->assertCount(4, $flowSourceMessageKeys);
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
        $this->assertSame(FailStepMock::class, $exception->getStepSource());
        $this->assertStringStartsWith('Test Exception', $exception->getMessage());
    }

    public function testRunWithEmptyInitMessage(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.empty.v1',
            flowSource: WorkflowEmptyMock::class,
            storage: $this->storage(),
        );
        $flowRunner->run(new EmptyInitMessage());

        $flow = $flowRunner->getFlow();
        $this->assertInstanceOf(Flow::class, $flow);

        $this->assertCount(1, $flow->getFlowMessages());
        $this->assertSame(StatusEnum::OK, $flow->status());

        $flowMessageEvents = $this->client->keys('flow:message:*');
        $this->assertCount(1, $flowMessageEvents);
    }

    public function testPartialRerunInjectsHistoricalMessagesForFanIn(): void
    {
        $storage = $this->storage();

        // Run 1: vollständiger Flow → alle 6 FlowMessages werden gespeichert
        $runner1 = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $runner1->run(new MessageInitMock('initial'));

        $flowHash = $runner1->getFlow()->getHash();
        $this->assertCount(6, $runner1->getFlow()->getFlowMessages());

        // Run 2: nur PostStepMock, MessageDataMock als Eingabe
        // PostStepMock benötigt MessageDataMock (Entry ✓) + MessageDataSecondMock (FEHLT)
        // injectHistoricalMessages soll MessageDataSecondMock aus Run 1 injizieren
        $runner2 = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $result = $runner2->run(
            new MessageDataMock('re-run'),
            flowHash: $flowHash,
            includeSteps: [PostStepMock::class],
        );

        $flow2 = $runner2->getFlow();
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertInstanceOf(Flow::class, $flow2);
        $this->assertCount(0, $flow2->getFlowExceptions());

        // PostStepMock: MessageDataMock (Entry) + MessageDataSecondMock (injiziert) + Return = 3
        $this->assertCount(3, $flow2->getFlowMessages());

        // Injizierter MessageDataSecondMock-Eintrag muss vorhanden sein
        $injected = array_values(array_filter(
            $flow2->getFlowMessages(),
            static fn (FlowMessage $flowMessage): bool => $flowMessage->getMessageSource() === MessageDataSecondMock::class,
        ));
        $this->assertCount(1, $injected);

        // Run 2 soll 3 neue FlowMessages in Storage persistiert haben (Run 1 hatte 6)
        $flowMessageEvents = $this->client->keys('flow:message:*');
        $this->assertCount(9, $flowMessageEvents);
    }

    public function testPartialRerunWithMessageDataSecondMockInjectsMessageDataMock(): void
    {
        $storage = $this->storage();

        // Run 1: vollständiger Flow → alle 6 FlowMessages werden gespeichert
        $runner1 = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $runner1->run(new MessageInitMock('initial'));

        $flowHash = $runner1->getFlow()->getHash();

        // Run 2: nur PostStepMock, MessageDataSecondMock als Eingabe
        // PostStepMock benötigt MessageDataSecondMock (Entry ✓) + MessageDataMock (FEHLT)
        // injectHistoricalMessages soll MessageDataMock aus Run 1 injizieren
        $runner2 = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $storage,
        );
        $result = $runner2->run(
            new MessageDataSecondMock('re-run', new MessageSubDataMock('alien')),
            flowHash: $flowHash,
            includeSteps: [PostStepMock::class],
        );

        $flow2 = $runner2->getFlow();
        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertInstanceOf(Flow::class, $flow2);
        $this->assertCount(0, $flow2->getFlowExceptions());

        // PostStepMock: MessageDataSecondMock (Entry) + MessageDataMock (injiziert) + Return = 3
        $this->assertCount(3, $flow2->getFlowMessages());

        // Injizierter MessageDataMock-Eintrag muss vorhanden sein
        $injected = array_values(array_filter(
            $flow2->getFlowMessages(),
            static fn (FlowMessage $flowMessage): bool => $flowMessage->getMessageSource() === MessageDataMock::class,
        ));
        $this->assertCount(1, $injected);
    }
}
