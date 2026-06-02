<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageReturnMock;
use Tests\MockClass\ValidProjectionHandlerMock;
use Wundii\Flowcrafter\Attribute\FlowProjection;
use Wundii\Flowcrafter\Attribute\FlowProjectionMessage;
use Wundii\Flowcrafter\FlowMessageReadonly;
use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;
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
        $this->assertSame([
            MessageInitMock::class => 'onInit',
            MessageReturnMock::class => 'onReturn',
        ], $meta->messageMethods);
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

    public function testDiscoverDuplicateMessageSourceThrows(): void
    {
        // Anonymous fixture so the intentionally invalid handler never lands in
        // the autoload classmap and trips up real ProjectionDiscovery::discover().
        $handler = new #[FlowProjection(['flow.test.dup'])] class implements ProjectionHandlerInterface {
            #[FlowProjectionMessage(MessageInitMock::class)]
            public function first(FlowMessageReadonly $flowMessageReadonly): void
            {
            }

            #[FlowProjectionMessage(MessageInitMock::class)]
            public function second(FlowMessageReadonly $flowMessageReadonly): void
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is already registered by method');

        ProjectionDiscovery::discover([$handler::class]);
    }

    public function testDiscoverMethodWithoutFlowMessageReadonlyParamThrows(): void
    {
        $handler = new #[FlowProjection(['flow.test.inv'])] class implements ProjectionHandlerInterface {
            #[FlowProjectionMessage(MessageInitMock::class)]
            public function onInit(string $notAFlowMessage): void
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must declare a parameter of type');

        ProjectionDiscovery::discover([$handler::class]);
    }
}
