<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class FlowProjection
{
    /**
     * @var string[]
     */
    public array $flowTypes;

    /**
     * @param string[] $flowTypes
     */
    public function __construct(
        string|array $flowTypes,
    ) {
        $this->flowTypes = is_array($flowTypes) ? $flowTypes : [$flowTypes];
    }
}
