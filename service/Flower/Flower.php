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

class Flower
{
    private static ?Flower $flower = null;

    private Request $request;

    private Router $router;

    private function __construct()
    {
    }

    public static function router(): Router
    {
        $flower = self::getInstance();

        return $flower->router;
    }

    public static function run(): void
    {
        $flower = self::getInstance();
        $request = $flower->request;
        $router = $flower->router();
        $response = null;

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
        } catch (ReflectionException $e) {
            $response = new Response('Internal Server Error: ' . $e->getMessage(), 500);
        }

        $response->send();
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
