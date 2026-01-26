<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Enum;

use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

enum MessageEnum: string
{
    case INIT = 'init';
    case DATA = 'stub';
    case RETURN = 'return';

    /**
     * @return class-string
     */
    public function interface(): string
    {
        return match ($this) {
            self::INIT => MessageInitInterface::class,
            self::DATA => MessageDataInterface::class,
            self::RETURN => MessageReturnInterface::class,
        };
    }
}
