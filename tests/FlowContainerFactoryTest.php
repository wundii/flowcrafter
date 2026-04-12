<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\MockClass\DependencyInterfaceImplMock;
use Tests\MockClass\DependencyInterfaceMock;
use Tests\MockClass\DependencyMock;
use Tests\MockClass\InterfaceBindingStubMock;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowInterfaceBindingMock;
use Wundii\Flowcrafter\FlowContainerFactory;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

final class FlowContainerFactoryTest extends TestCase
{
    public function testBuildWithClassStringDependency(): void
    {
        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [],
            syntheticServices: [],
            dependencies: [DependencyMock::class],
        );

        $this->assertTrue($containerBuilder->has(DependencyMock::class));
    }

    public function testBuildWithObjectDependency(): void
    {
        $dependencyInterfaceImplMock = new DependencyInterfaceImplMock();

        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [],
            syntheticServices: [],
            dependencies: [$dependencyInterfaceImplMock],
        );

        $this->assertTrue($containerBuilder->has(DependencyInterfaceImplMock::class));
        $this->assertSame($dependencyInterfaceImplMock, $containerBuilder->get(DependencyInterfaceImplMock::class));
    }

    public function testBuildWithInterfaceBinding(): void
    {
        $containerBuilder = FlowContainerFactory::build(
            autowireClasses: [InterfaceBindingStubMock::class],
            syntheticServices: [new MessageInitMock('hello')],
            dependencies: [
                DependencyInterfaceMock::class => new DependencyInterfaceImplMock(),
            ],
        );

        $stub = $containerBuilder->get(InterfaceBindingStubMock::class);
        $this->assertInstanceOf(InterfaceBindingStubMock::class, $stub);

        $messageReturn = $stub->process();
        $this->assertSame('interface-binding-works:hello', $messageReturn->getData());
    }

    public function testBuildWithInterfaceBindingFailsWithoutBinding(): void
    {
        $this->expectException(RuntimeException::class);

        FlowContainerFactory::build(
            autowireClasses: [InterfaceBindingStubMock::class],
            syntheticServices: [new MessageInitMock('hello')],
            dependencies: [],
        );
    }

    public function testFlowRunnerWithInterfaceBinding(): void
    {
        $flowRunner = new FlowRunner(
            type: 'flow.interface.binding.v1',
            flowSource: WorkflowInterfaceBindingMock::class,
            dependenciesInjection: [
                DependencyInterfaceMock::class => new DependencyInterfaceImplMock(),
            ],
        );

        $result = $flowRunner->run(new MessageInitMock('world'));

        $this->assertInstanceOf(MessageReturnInterface::class, $result);
        $this->assertSame('interface-binding-works:world', $result->getData());
    }
}
