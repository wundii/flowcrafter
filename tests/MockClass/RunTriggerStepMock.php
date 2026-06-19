<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractStep;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

class RunTriggerStepMock extends AbstractStep
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
        $result = $this->run(
            flowSource: WorkflowMock::class,
            message: new MessageInitMock($this->messageInitMock->getData()),
            flowSubject: 'inner-subject',
        );

        $innerData = $result instanceof MessageReturnInterface ? $result->getData() : 'no-return';

        return new MessageReturnMock('outer: ' . $innerData);
    }
}
