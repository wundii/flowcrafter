<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Projection;

use Wundii\Flowcrafter\FlowMessageReadonly;

final readonly class ProjectionQueueItem
{
    public function __construct(
        private string $itemId,
        private string $handlerClass,
        private FlowMessageReadonly $flowMessageReadonly,
    ) {
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getHandlerClass(): string
    {
        return $this->handlerClass;
    }

    public function getFlowMessageReadonly(): FlowMessageReadonly
    {
        return $this->flowMessageReadonly;
    }
}
