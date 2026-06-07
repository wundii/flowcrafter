<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\DependencyInjection;

use Closure;
use Psr\Container\ContainerInterface;
use Wundii\Flowcrafter\ClassResolver;

/**
 * Fluent registry for custom dependency injection.
 *
 * Replaces the previous mixed array passed to FlowcrafterConfig::setDependencyRegistry().
 * Each registration mode is a named method instead of an implicit key/value convention:
 *
 *   - instance()          synthetic instance, bound to its own class
 *   - bind()              interface (or any id) bound to a concrete object or autowired class
 *   - autowire()          single class autowired by the container
 *   - autowireNamespace() every instantiable class under a PSR-4 namespace prefix, autowired
 *   - autowireDirectory() every instantiable class under a directory, autowired
 *   - factory()           lazy closure receiving the PSR-11 container, optional interface alias
 */
final class DependencyRegistry
{
    /**
     * @var list<object>
     */
    private array $instances = [];

    /**
     * @var array<class-string, object|class-string>
     */
    private array $bindings = [];

    /**
     * @var list<class-string>
     */
    private array $autowireClasses = [];

    /**
     * @var list<string>
     */
    private array $autowireNamespaces = [];

    /**
     * @var list<string>
     */
    private array $autowireDirectories = [];

    /**
     * @var array<class-string, array{Closure(ContainerInterface): object, class-string|null}>
     */
    private array $factories = [];

    /**
     * Register a ready-made instance, bound to its own class.
     */
    public function instance(object $service): self
    {
        $this->instances[] = $service;

        return $this;
    }

    /**
     * Bind an id (typically an interface) to a concrete object or an autowired class.
     *
     * @param class-string             $id
     * @param object|class-string $concrete
     */
    public function bind(string $id, object|string $concrete): self
    {
        $this->bindings[$id] = $concrete;

        return $this;
    }

    /**
     * Autowire a single class by its name.
     *
     * @param class-string $class
     */
    public function autowire(string $class): self
    {
        $this->autowireClasses[] = $class;

        return $this;
    }

    /**
     * Autowire every instantiable class under a PSR-4 namespace prefix.
     */
    public function autowireNamespace(string $namespacePrefix): self
    {
        $this->autowireNamespaces[] = $namespacePrefix;

        return $this;
    }

    /**
     * Autowire every instantiable class located under a directory.
     */
    public function autowireDirectory(string $directory): self
    {
        $this->autowireDirectories[] = $directory;

        return $this;
    }

    /**
     * Register a lazy factory. The closure receives the PSR-11 container and returns the service.
     *
     * @param class-string                       $id
     * @param Closure(ContainerInterface): object $factory
     * @param class-string|null                  $alias optional interface to alias to the produced service
     */
    public function factory(string $id, Closure $factory, ?string $alias = null): self
    {
        $this->factories[$id] = [$factory, $alias];

        return $this;
    }

    /**
     * @return list<object>
     */
    public function getInstances(): array
    {
        return $this->instances;
    }

    /**
     * @return array<class-string, object|class-string>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * @return array<class-string, array{Closure(ContainerInterface): object, class-string|null}>
     */
    public function getFactories(): array
    {
        return $this->factories;
    }

    /**
     * Explicitly registered single classes to autowire (public services). Classes already covered
     * by an instance, binding or factory are excluded so they are not registered twice.
     *
     * @return list<class-string>
     */
    public function getAutowireClasses(): array
    {
        $excluded = $this->coveredClasses();

        $classes = array_filter(
            $this->autowireClasses,
            static fn (string $class): bool => !in_array($class, $excluded, true),
        );

        return array_values(array_unique($classes));
    }

    /**
     * Classes discovered under the registered namespaces and directories (registered as private
     * services: unused ones are pruned, referenced ones injected by reference). Classes covered by
     * an explicit registration are excluded so the explicit one wins.
     *
     * @return list<class-string>
     */
    public function getAutowireBulkClasses(): array
    {
        $classes = [];

        foreach ($this->autowireNamespaces as $autowireNamespace) {
            $classes = [...$classes, ...ClassResolver::resolveByNamespace($autowireNamespace)];
        }

        foreach ($this->autowireDirectories as $autowireDirectory) {
            $classes = [...$classes, ...ClassResolver::resolveByDirectory($autowireDirectory)];
        }

        $excluded = [...$this->coveredClasses(), ...$this->autowireClasses];

        $classes = array_filter(
            $classes,
            static fn (string $class): bool => !in_array($class, $excluded, true),
        );

        return array_values(array_unique($classes));
    }

    /**
     * Classes already covered by an instance, binding or factory.
     *
     * @return list<class-string>
     */
    private function coveredClasses(): array
    {
        $covered = [];

        foreach ($this->instances as $instance) {
            $covered[] = $instance::class;
        }

        foreach ($this->bindings as $binding) {
            $covered[] = is_object($binding) ? $binding::class : $binding;
        }

        foreach (array_keys($this->factories) as $id) {
            $covered[] = $id;
        }

        return $covered;
    }
}
