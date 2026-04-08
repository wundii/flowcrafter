<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;

#[FlowSchedule('*/5 * * * *', name: 'test-fail-schedule')]
class ScheduleFlowFailMock extends AbstractSchedule
{
    public bool $executed = false;

    public function process(): void
    {
        $this->executed = true;

        $animals = [
            'Kalb',
            'Lamm',
            'Rind',
            'Schwein',
        ];

        $animal = $animals[array_rand($animals)];

        $this->enqueue(
            flowSource: WorkflowFailMock::class,
            message: new MessageInitMock($animal),
            flowSubject: 'test-fail-subject-' . $animal,
        );
    }
}
