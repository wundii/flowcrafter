<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

class WorkflowMock implements FlowInterface
{
    public static function schema(): FlowSchema
    {
        return new FlowSchema();
    }
}
