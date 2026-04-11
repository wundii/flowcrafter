<?php

declare(strict_types=1);

namespace Wundii\Flower;

use Closure;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Throwable;

class Flower
{
    private static ?Flower $flower = null;

    private Request $request;

    private Router $router;

    private function __construct()
    {
    }

    public static function reset(): void
    {
        self::$flower = null;
        Router::reset();
    }

    public static function resetRequest(): void
    {
        if (self::$flower instanceof self) {
            self::$flower->request = Request::createFromGlobals();
        }
    }

    public static function router(): Router
    {
        $flower = self::getInstance();

        return $flower->router;
    }

    /**
     * @param (Closure(Throwable): Response)|null $errorHandler
     */
    public static function run(?string $secret = null, ?Closure $errorHandler = null): void
    {
        self::handle($secret, errorHandler: $errorHandler)->send();
    }

    /**
     * @param (Closure(Throwable): Response)|null $errorHandler
     */
    public static function handle(?string $secret = null, ?Request $request = null, ?Closure $errorHandler = null): Response
    {
        $flower = self::getInstance();
        $request = $request ?? $flower->request;
        $router = $flower->router();
        $response = null;

        $publicPaths = ['/', '/metrics'];
        if ($secret !== null && $secret !== '' && !in_array($request->getPathInfo(), $publicPaths, true)) {
            $authHeader = (string) $request->headers->get('Authorization', '');
            $provided = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : '';
            if (!hash_equals($secret, $provided)) {
                return new Response('Unauthorized', 401);
            }
        }

        $requestContext = new RequestContext();
        $requestContext->fromRequest($request);

        $urlMatcher = new UrlMatcher($router->routeCollection(), $requestContext);
        try {
            $parameters = $urlMatcher->match($request->getPathInfo());
            $handlers = $router->handlerCollection();

            $handler = $handlers[$parameters['_route']] ?? null;
            if ($handler instanceof Closure) {
                $response = self::acceptsRequestHandler($handler)
                    ? $handler($request)
                    : $handler();
            }

            if (!$response instanceof Response) {
                $response = new Response('Not Found', 404);
            }
        } catch (ResourceNotFoundException) {
            $response = new Response('Not Found', 404);
        } catch (Throwable $throwable) {
            $response = $errorHandler instanceof \Closure
                ? $errorHandler($throwable)
                : new Response('Internal Server Error: ' . $throwable->getMessage(), 500);
        }

        return $response;
    }

    /**
     * @param callable(Request): Response $handler
     * @throws ReflectionException
     */
    public static function acceptsRequestHandler(callable $handler): bool
    {
        if (!$handler instanceof Closure) {
            return false;
        }

        $reflectionFunction = new ReflectionFunction($handler);

        foreach ($reflectionFunction->getParameters() as $reflectionParameter) {
            $type = $reflectionParameter->getType();

            if (
                $type instanceof ReflectionNamedType
                && !$type->isBuiltin()
                && $type->getName() === Request::class
            ) {
                return true;
            }
        }

        return false;
    }

    private static function getInstance(): self
    {
        if (!self::$flower instanceof self) {
            self::$flower = new self();

            self::$flower->request = Request::createFromGlobals();
            self::$flower->router = Router::create();
        }

        return self::$flower;
    }
}
