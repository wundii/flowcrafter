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
use Tests\Trait\MySqlClientTestTrait;
use Wundii\Flowcrafter\EmptyInitMessage;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowException;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
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

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_RESULT);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_SOURCE_STEP);
        $this->assertCount(4, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_SOURCE_MESSAGE);
        $this->assertCount(4, iterator_to_array($stmt->fetchAll()));
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

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_MESSAGE);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));
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
        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_MESSAGE);
        $this->assertCount(9, iterator_to_array($stmt->fetchAll()));
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
