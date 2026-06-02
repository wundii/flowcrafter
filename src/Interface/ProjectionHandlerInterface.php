<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Interface;

/**
 * Marker interface for projection handlers. Concrete handlers carry the
 * #[FlowProjection] class attribute (flow types) and bind methods to message
 * sources via the #[FlowProjectionMessage] method attribute. The projection
 * worker resolves and invokes the matching method per message.
 */
interface ProjectionHandlerInterface
{
}
