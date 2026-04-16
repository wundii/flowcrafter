<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

interface ScheduleInterface
{
    public function process(): void;
}
