<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

use Wundii\Flowcrafter\FlowMessageReadonly;

interface ProjectionHandlerInterface
{
    public function project(FlowMessageReadonly $flowMessageReadonly): void;
}
