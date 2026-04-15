<?php

declare(strict_types=1);

namespace Tests\Service\Flower;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wundii\Flower\Flower;
use Wundii\Flower\MethodEnum;

final class FlowerTest extends TestCase
{
    protected function setUp(): void
    {
        Flower::reset();
    }

    protected function tearDown(): void
    {
        Flower::reset();
    }

    public function testHandleDispatchesToRegisteredHandler(): void
    {
        $this->registerRoute('/hello', MethodEnum::GET, static fn (): Response => new Response('world', 200));

        $request = Request::create('/hello');
        $response = Flower::handle(request: $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('world', $response->getContent());
    }

    public function testHandlePassesRequestToHandlerThatAcceptsIt(): void
    {
        $this->registerRoute('/echo', MethodEnum::GET, static function (Request $request): Response {
            return new Response($request->query->get('msg', ''));
        });

        $request = Request::create('/echo', 'GET', [
            'msg' => 'hello',
        ]);
        $response = Flower::handle(request: $request);

        $this->assertSame('hello', $response->getContent());
    }

    public function testHandleCallsHandlerWithoutRequestWhenNotRequired(): void
    {
        $this->registerRoute('/no-request', MethodEnum::GET, static fn (): Response => new Response('no-req'));

        $request = Request::create('/no-request');
        $response = Flower::handle(request: $request);

        $this->assertSame('no-req', $response->getContent());
    }

    public function testHandleReturns404ForUnknownRoute(): void
    {
        $request = Request::create('/nonexistent');
        $response = Flower::handle(request: $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testHandleReturns401WhenNoAuthorizationHeader(): void
    {
        $this->registerRoute('/api/protected', MethodEnum::GET, static fn (): Response => new Response('ok'));

        $request = Request::create('/api/protected');
        $response = Flower::handle(secret: 'my-secret', request: $request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testHandleReturns401ForWrongToken(): void
    {
        $this->registerRoute('/api/protected', MethodEnum::GET, static fn (): Response => new Response('ok'));

        $request = Request::create('/api/protected');
        $request->headers->set('Authorization', 'Bearer wrong-token');

        $response = Flower::handle(secret: 'my-secret', request: $request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testHandleAllowsAccessWithCorrectToken(): void
    {
        $this->registerRoute('/api/protected', MethodEnum::GET, static fn (): Response => new Response('ok'));

        $request = Request::create('/api/protected');
        $request->headers->set('Authorization', 'Bearer my-secret');

        $response = Flower::handle(secret: 'my-secret', request: $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHandleSkipsAuthForRootPath(): void
    {
        $this->registerRoute('/', MethodEnum::GET, static fn (): Response => new JsonResponse([
            'status' => 'ok',
        ]));

        $request = Request::create('/');
        $response = Flower::handle(secret: 'my-secret', request: $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHandleSkipsAuthForMetricsPath(): void
    {
        $this->registerRoute('/metrics', MethodEnum::GET, static fn (): Response => new Response('metrics'));

        $request = Request::create('/metrics');
        $response = Flower::handle(secret: 'my-secret', request: $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHandleSkipsAuthWhenNoSecretConfigured(): void
    {
        $this->registerRoute('/api/open', MethodEnum::GET, static fn (): Response => new Response('open'));

        $request = Request::create('/api/open');
        $response = Flower::handle(secret: null, request: $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHandleReturns500WhenHandlerThrowsAndNoErrorHandler(): void
    {
        $this->registerRoute('/api/boom', MethodEnum::GET, static function (): never {
            throw new RuntimeException('kaboom');
        });

        $request = Request::create('/api/boom');
        $request->headers->set('Authorization', 'Bearer secret');

        $response = Flower::handle(secret: 'secret', request: $request);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('kaboom', (string) $response->getContent());
    }

    public function testHandleCallsCustomErrorHandler(): void
    {
        $this->registerRoute('/api/fail', MethodEnum::GET, static function (): never {
            throw new RuntimeException('expected error');
        });

        $request = Request::create('/api/fail');
        $request->headers->set('Authorization', 'Bearer secret');

        $response = Flower::handle(
            secret: 'secret',
            request: $request,
            errorHandler: static fn (\Throwable $throwable): Response => new Response('caught: ' . $throwable->getMessage(), 422),
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('caught: expected error', $response->getContent());
    }

    public function testAcceptsRequestHandlerReturnsTrueForClosureWithRequest(): void
    {
        $handler = static fn (Request $request): Response => new Response();

        $this->assertTrue(Flower::acceptsRequestHandler($handler));
    }

    public function testAcceptsRequestHandlerReturnsFalseForClosureWithoutRequest(): void
    {
        $handler = static fn (): Response => new Response();

        $this->assertFalse(Flower::acceptsRequestHandler($handler));
    }

    public function testAcceptsRequestHandlerReturnsFalseForClosureWithStringParam(): void
    {
        $handler = static fn (string $foo): Response => new Response($foo);

        $this->assertFalse(Flower::acceptsRequestHandler($handler));
    }

    public function testAcceptsRequestHandlerReturnsFalseForNonClosure(): void
    {
        $this->assertFalse(Flower::acceptsRequestHandler('strlen'));
    }

    public function testResetClearsAllRegisteredRoutes(): void
    {
        $this->registerRoute('/before-reset', MethodEnum::GET, static fn (): Response => new Response('ok'));
        Flower::reset();

        $request = Request::create('/before-reset');
        $response = Flower::handle(request: $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testRouterReturnsSameInstanceWithinOneSetup(): void
    {
        $routerA = Flower::router();
        $routerB = Flower::router();

        $this->assertSame($routerA, $routerB);
    }

    private function registerRoute(string $path, MethodEnum $methodEnum, callable $handler): void
    {
        Flower::router()->add($path, $methodEnum, $handler);
    }
}
