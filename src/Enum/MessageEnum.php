<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Enum;

enum MessageEnum: string
{
    case INIT = 'init';
    case DATA = 'stub';
    case RETURN = 'return';
}
