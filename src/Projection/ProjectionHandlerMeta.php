<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Projection;

use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;

final readonly class ProjectionHandlerMeta
{
    /**
     * @param class-string<ProjectionHandlerInterface> $handlerClass
     * @param string[] $flowTypes
     * @param array<class-string, string> $messageMethods messageSource => handler method name
     */
    public function __construct(
        public string $handlerClass,
        public array $flowTypes,
        public array $messageMethods = [],
    ) {
    }
}
