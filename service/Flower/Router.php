<?php

declare(strict_types=1);

namespace Wundii\Flower;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Router
{
    private static ?Router $router = null;

    private RouteCollection $routeCollection;

    /**
     * @var array<string, callable(Request): Response|callable(): Response>
     */
    private array $handlerCollection = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        if (!self::$router instanceof self) {
            self::$router = new self();
            self::$router->routeCollection = new RouteCollection();
        }

        return self::$router;
    }

    /**
     * @param callable(Request): Response $handler
     */
    public function add(
        string $path,
        MethodEnum $methodEnum,
        callable $handler,
    ): void {
        $this->routeCollection->add($path, new Route(
            path: $path,
            methods: [$methodEnum->value]
        ));

        $this->handlerCollection[$path] = $handler;
    }

    public function routeCollection(): RouteCollection
    {
        return $this->routeCollection;
    }

    /**
     * @return array<string, callable(Request): Response|callable(): Response>
     */
    public function handlerCollection(): array
    {
        return $this->handlerCollection;
    }

    public static function reset(): void
    {
        self::$router = null;
    }
}
