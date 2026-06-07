<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\DependencyConstructMock;
use Tests\MockClass\DependencyInterfaceImplMock;
use Tests\MockClass\DependencyInterfaceMock;
use Tests\MockClass\DependencyMock;
use Wundii\Flowcrafter\ClassResolver;

final class ClassResolverTest extends TestCase
{
    public function testResolveByNamespaceReturnsInstantiableClasses(): void
    {
        $classNames = ClassResolver::resolveByNamespace('Tests\\MockClass');

        $this->assertContains(DependencyMock::class, $classNames);
        $this->assertContains(DependencyConstructMock::class, $classNames);
        $this->assertContains(DependencyInterfaceImplMock::class, $classNames);
    }

    public function testResolveByNamespaceExcludesInterfaces(): void
    {
        $classNames = ClassResolver::resolveByNamespace('Tests\\MockClass');

        $this->assertNotContains(DependencyInterfaceMock::class, $classNames);
    }

    public function testResolveByNamespaceFiltersByPrefix(): void
    {
        $classNames = ClassResolver::resolveByNamespace('Tests\\MockClass');

        foreach ($classNames as $className) {
            $this->assertStringStartsWith('Tests\\MockClass', $className);
        }
    }

    public function testResolveByDirectoryReturnsInstantiableClasses(): void
    {
        $classNames = ClassResolver::resolveByDirectory(__DIR__ . '/MockClass');

        $this->assertContains(DependencyMock::class, $classNames);
        $this->assertNotContains(DependencyInterfaceMock::class, $classNames);
    }

    public function testResolveByDirectoryReturnsEmptyForMissingDirectory(): void
    {
        $classNames = ClassResolver::resolveByDirectory(__DIR__ . '/does-not-exist');

        $this->assertSame([], $classNames);
    }
}
