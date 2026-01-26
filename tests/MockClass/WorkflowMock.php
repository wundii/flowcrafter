<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

class WorkflowMock implements FlowInterface
{
    public static function schema(): FlowSchema
    {
        $flowBuilder = new FlowBuilder(
            'flow.workflow.v1',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStub(
            StubMock::class,
            MessageInitMock::class,
        );

        $flowBuilder->addStub(
            NextStubMock::class,
            MessageDataMock::class,
        );

        $flowBuilder->addStub(
            OtherStubMock::class,
            MessageDataMock::class,
        );

        return $flowBuilder->build();
    }
}
