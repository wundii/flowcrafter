<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Projection;

use Wundii\Flowcrafter\FlowMessageReadonly;

final readonly class ProjectionQueueItem
{
    public function __construct(
        private string $itemId,
        private FlowMessageReadonly $flowMessageReadonly,
    ) {
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getFlowMessageReadonly(): FlowMessageReadonly
    {
        return $this->flowMessageReadonly;
    }
}
