<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\ObserveItem;

interface StorageInterface
{
    public function initializeDatabase(): void;

    public function registeredFlowSchema(FlowSchema $flowSchema): void;

    public function registeredFlow(Flow $flow): void;

    public function writeFlow(Flow $flow, ?int $queueId = null): void;

    public function writeFlowMessage(FlowMessage $flowMessage): void;

    /**
     * @return iterable<ObserveItem>
     */
    public function observeQueue(): iterable;
}
