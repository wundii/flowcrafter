<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\MessageInitInterface;

/**
 * Generic init message for flows whose first step needs no external input.
 *
 * Usage: declare it as a `public readonly` promoted property in the init step
 * so Rector's unused-constructor-param rule does not strip it. The property is
 * never read — it only carries the routing signal:
 *
 *     public function __construct(
 *         public readonly EmptyInitMessage $init,
 *     ) {}
 */
final readonly class EmptyInitMessage extends AbstractMessage implements MessageInitInterface
{
}
