<?php

declare(strict_types=1);

namespace Tests\Trait;

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flower\Flower;
use Wundii\Service\Routes;

trait ApiTestTrait
{
    private StorageInterface&MockObject $storage;

    private FlowcrafterConfig $flowcrafterConfig;

    protected function setUpApi(): void
    {
        Flower::reset();

        $this->storage = $this->createMock(StorageInterface::class);

        $this->flowcrafterConfig = new FlowcrafterConfig();
        $this->flowcrafterConfig->setServerSecret('test-secret');
        $this->flowcrafterConfig->setServerDescription('Test Instance');

        Routes::service($this->flowcrafterConfig, $this->storage);
    }

    protected function tearDownApi(): void
    {
        Flower::reset();
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     */
    protected function apiRequest(
        string $method,
        string $uri,
        ?string $secret = 'test-secret',
        array $query = [],
        ?string $body = null,
        array $headers = [],
    ): Response {
        $queryString = $query !== [] ? '?' . http_build_query($query) : '';

        $request = Request::create(
            uri: $uri . $queryString,
            method: $method,
            content: $body ?? '',
        );

        if ($secret !== null) {
            $request->headers->set('Authorization', 'Bearer ' . $secret);
        }

        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        return Flower::handle(
            secret: $this->flowcrafterConfig->getServerSecret(),
            request: $request,
        );
    }

    /**
     * @return array<mixed>
     */
    protected function jsonDecode(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true);
        self::assertIsArray($data);

        return $data;
    }
}
