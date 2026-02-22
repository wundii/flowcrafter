<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowSchema;

interface StorageInterface
{
    public function initializeDatabase(): void;

    public function registeredFlowSchema(FlowSchema $flowSchema): void;

    public function registeredFlow(Flow $flow): void;

    public function writeFlow(Flow $flow): void;

    public function writeFlowMessage(FlowMessage $flowMessage): void;
}
