<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Attribute;

use Attribute;
use Wundii\Flowcrafter\Interface\MessageInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class FlowProjectionMessage
{
    /**
     * @param class-string<MessageInterface> $messageSource
     */
    public function __construct(
        public string $messageSource,
    ) {
    }
}
