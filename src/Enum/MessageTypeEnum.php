<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Enum;

enum MessageTypeEnum: string
{
    case PROCESS = 'process';
    case FINISH = 'finish';

    public function isProcess(): bool
    {
        return $this === self::PROCESS;
    }
}
