<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

use Wundii\Flowcrafter\FlowSchema;

interface FlowInterface
{
    public static function schema(): FlowSchema;
}
