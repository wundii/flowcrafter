<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class FlowProjection
{
    /**
     * @param string[] $flowTypes
     */
    public function __construct(
        public array $flowTypes,
    ) {
    }
}
