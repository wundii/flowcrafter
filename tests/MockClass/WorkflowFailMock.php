<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

class WorkflowFailMock implements FlowInterface
{
    public static function schema(): FlowSchema
    {
        $flowBuilder = new FlowBuilder(
            'flow.workflow.fail.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStub(StubMock::class);
        $flowBuilder->addStub(FailStubMock::class);

        return $flowBuilder->build();
    }
}
