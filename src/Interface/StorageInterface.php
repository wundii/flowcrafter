<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowMessage;
use Wundii\Flowcrafter\FlowSchema;

interface StorageInterface
{
    public function initialize(): void;

    public function registeredFlowSchema(FlowSchema $flowSchema): void;

    public function writeFlow(Flow $flow): void;

    public function writeFlowMessage(FlowMessage $flowMessage): void;
}
