<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractStep;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

class EnqueueTriggerStepMock extends AbstractStep
{
    public function __construct(
        private readonly MessageInitMock $messageInitMock,
    ) {
    }

    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [
            MessageReturnMock::class,
        ];
    }

    public function process(): MessageReturnInterface
    {
        $this->enqueue(
            flowSource: WorkflowMock::class,
            message: new MessageInitMock($this->messageInitMock->getData()),
            flowSubject: 'enqueued-subject',
        );

        return new MessageReturnMock('enqueued');
    }
}
