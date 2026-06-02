<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

interface QueueConfigInterface
{
    public function getQueueClass(): string;
}
