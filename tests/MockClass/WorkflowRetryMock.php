<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Attribute\FlowGroup;
use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

#[FlowGroup('fail-group')]
class WorkflowRetryMock implements FlowInterface
{
    public static function schema(): FlowSchema
    {
        $flowBuilder = new FlowBuilder(
            'flow.workflow.retry.v2',
            MessageInitMock::class,
            MessageReturnMock::class,
        );

        $flowBuilder->addStep(StepMock::class);
        $flowBuilder->addStep(RetryStepMock::class, retries: 3, delay: 100);

        return $flowBuilder->build();
    }
}
