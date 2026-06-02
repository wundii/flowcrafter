<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Projection;

use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;

final readonly class ProjectionHandlerMeta
{
    /**
     * @param class-string<ProjectionHandlerInterface> $handlerClass
     * @param string[] $flowTypes
     */
    public function __construct(
        public string $handlerClass,
        public array $flowTypes,
    ) {
    }
}
