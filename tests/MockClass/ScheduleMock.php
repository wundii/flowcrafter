<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;

#[FlowSchedule('*/5 * * * *', name: 'test-schedule', active: false)]
class ScheduleMock extends AbstractSchedule
{
    public function process(): void
    {
        $animals = [
            'Aal',
            'Ente',
            'Fisch',
            'Forelle',
            'Gans',
            'Hirsch',
            'Huhn',
            'Kabeljau',
            'Kalb',
            'Kaninchen',
            'Lachs',
            'Lamm',
            'Makrele',
            'Pute',
            'Reh',
            'Rind',
            'Sardine',
            'Schwein',
            'Thunfisch',
            'Wildschwein',
        ];

        $animal = $animals[array_rand($animals)];

        $this->enqueue(
            flowSource: WorkflowMock::class,
            message: new MessageInitMock($animal),
            flowSubject: 'test-subject-' . random_int(1, 99999),
        );
    }
}
