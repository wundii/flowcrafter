<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Enum;

enum MessageTypeEnum: string
{
    case PROCESS = 'process';
    case PROCESS_MODIFIED = 'process_modified';
    case FINISH = 'finish';

    public function isProcess(): bool
    {
        return $this === self::PROCESS || $this === self::PROCESS_MODIFIED;
    }
}
