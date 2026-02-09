<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Enum;

enum MessageTypeEnum: string
{
    case WAIT = 'wait';
    case PROCESS = 'process';
    case FINISH = 'finish';
}
