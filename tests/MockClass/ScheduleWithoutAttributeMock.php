<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\ScheduleInterface;

class ScheduleWithoutAttributeMock implements ScheduleInterface
{
    public function process(): void
    {
    }
}
