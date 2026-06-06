<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Projection;

use Throwable;
use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;

final readonly class ProjectionResult
{
    /**
     * @param class-string<ProjectionHandlerInterface> $handlerClass
     */
    public function __construct(
        public string $handlerClass,
        public string $method,
        public string $messageSource,
        public string $content,
        public ?Throwable $throwable,
    ) {
    }
}
