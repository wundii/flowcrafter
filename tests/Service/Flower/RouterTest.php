<?php

declare(strict_types=1);

namespace Tests\Service\Flower;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Wundii\Flower\MethodEnum;
use Wundii\Flower\Router;

final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        Router::reset();
    }

    protected function tearDown(): void
    {
        Router::reset();
    }

    public function testCreateReturnsSingleton(): void
    {
        $routerA = Router::create();
        $routerB = Router::create();

        $this->assertSame($routerA, $routerB);
    }

    public function testResetClearsSingleton(): void
    {
        $routerA = Router::create();
        Router::reset();
        $routerB = Router::create();

        $this->assertNotSame($routerA, $routerB);
    }

    public function testAddRegistersRouteInRouteCollection(): void
    {
        $router = Router::create();
        $router->add('/test', MethodEnum::GET, static fn (): Response => new Response('ok'));

        $route = $router->routeCollection()->get('/test');
        $this->assertInstanceOf(\Symfony\Component\Routing\Route::class, $route);
        $this->assertContains('GET', $route->getMethods());
    }

    public function testAddRegistersHandlerInHandlerCollection(): void
    {
        $handler = static fn (): Response => new Response('ok');
        $router = Router::create();
        $router->add('/handler-test', MethodEnum::POST, $handler);

        $handlers = $router->handlerCollection();
        $this->assertArrayHasKey('/handler-test', $handlers);
        $this->assertSame($handler, $handlers['/handler-test']);
    }

    public function testAddMultipleRoutes(): void
    {
        $router = Router::create();
        $router->add('/route-a', MethodEnum::GET, static fn (): Response => new Response('a'));
        $router->add('/route-b', MethodEnum::POST, static fn (): Response => new Response('b'));

        $this->assertCount(2, $router->routeCollection());
        $this->assertCount(2, $router->handlerCollection());
    }

    public function testRouteUsesCorrectHttpMethod(): void
    {
        $router = Router::create();
        $router->add('/post-only', MethodEnum::POST, static fn (): Response => new Response());

        $route = $router->routeCollection()->get('/post-only');
        $this->assertInstanceOf(\Symfony\Component\Routing\Route::class, $route);
        $this->assertContains('POST', $route->getMethods());
        $this->assertNotContains('GET', $route->getMethods());
    }

    public function testHandlerCollectionIsInitiallyEmpty(): void
    {
        $router = Router::create();

        $this->assertSame([], $router->handlerCollection());
    }

    public function testRouteCollectionIsInitiallyEmpty(): void
    {
        $router = Router::create();

        $this->assertCount(0, $router->routeCollection());
    }

    public function testAddOverwritesExistingRouteWithSamePath(): void
    {
        $handlerA = static fn (): Response => new Response('a');
        $handlerB = static fn (): Response => new Response('b');

        $router = Router::create();
        $router->add('/duplicate', MethodEnum::GET, $handlerA);
        $router->add('/duplicate', MethodEnum::GET, $handlerB);

        $handlers = $router->handlerCollection();
        $this->assertSame($handlerB, $handlers['/duplicate']);
    }
}
