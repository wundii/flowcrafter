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

        $flowBuilder->addStub(StubMock::class);
        $flowBuilder->addStub(NextStubMock::class);
        $flowBuilder->addStub(OtherStubMock::class);
        $flowBuilder->addStub(PostStubMock::class);

        return $flowBuilder->build();
    }
}
