<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class FlowGroup
{
    public function __construct(
        public string $name,
    ) {
    }
}
