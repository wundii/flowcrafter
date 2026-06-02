<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\ValidProjectionHandlerMock;
use Wundii\Flowcrafter\Projection\ProjectionDiscovery;

final class ProjectionDiscoveryTest extends TestCase
{
    public function testDiscoverValidHandler(): void
    {
        $metas = ProjectionDiscovery::discover([ValidProjectionHandlerMock::class]);

        $this->assertCount(1, $metas);

        $meta = $metas[0];
        $this->assertSame(ValidProjectionHandlerMock::class, $meta->handlerClass);
        $this->assertSame(['flow.test.v1', 'flow.test.v2'], $meta->flowTypes);
    }

    public function testDiscoverDuplicateFlowTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is already registered by');

        ProjectionDiscovery::discover([
            ValidProjectionHandlerMock::class,
            ValidProjectionHandlerMock::class,
        ]);
    }
}
