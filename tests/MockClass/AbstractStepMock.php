<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractStep;
use Wundii\Flowcrafter\Interface\MessageDataInterface;

class AbstractStepMock extends AbstractStep
{
    public function __construct(
        private readonly MessageDataMock $messageDataMock,
    ) {
    }

    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [
            MessageAbstractStepMock::class,
        ];
    }

    public function process(): MessageDataInterface
    {
        return new MessageAbstractStepMock(
            $this->messageDataMock->getData(),
            $this->getFlowHash(),
            $this->getFlowRuntimeHash(),
            $this->getFlowType(),
            $this->getFlowSchemaHash(),
            $this->getFlowSubject(),
        );
    }
}
