<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Attribute;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class FlowEphemeral
{
    public function __construct(
        public int $expiryDays = 14,
    ) {
        if ($expiryDays < 1) {
            throw new InvalidArgumentException('FlowEphemeral expiryDays must be at least 1.');
        }
    }
}
