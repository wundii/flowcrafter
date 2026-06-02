<?php

declare(strict_types=1);

namespace Tests;

use ArrayIterator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Trait\ApiTestTrait;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\Storage\Entity\FlowListEntity;

final class ApiRouteTest extends TestCase
{
    use ApiTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApi();
    }

    protected function tearDown(): void
    {
        $this->tearDownApi();
        parent::tearDown();
    }

    public function testHealthEndpointReturnsOk(): void
    {
        $response = $this->apiRequest('GET', '/');
        $this->assertSame(200, $response->getStatusCode());
        $data = $this->jsonDecode($response);
        $this->assertSame('ok', $data['status']);
    }

    public function testPingEndpointReturnsPong(): void
    {
        $response = $this->apiRequest('GET', '/api/ping');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('"pong"', $response->getContent());
    }

    public function testNotFoundReturns404(): void
    {
        $response = $this->apiRequest('GET', '/nonexistent');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testProtectedEndpointRejectsNoAuth(): void
    {
        $response = $this->apiRequest('GET', '/api/ping', secret: null);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testProtectedEndpointRejectsWrongToken(): void
    {
        $response = $this->apiRequest('GET', '/api/ping', secret: 'wrong-token');
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testPublicEndpointSkipsAuth(): void
    {
        $response = $this->apiRequest('GET', '/', secret: null);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMetricsEndpointIsPublic(): void
    {
        $this->queue->method('openQueues')->willReturn(0);
        $this->storage->method('countFlows')->willReturn(0);
        $this->storage->method('countExceptions')->willReturn(0);

        $response = $this->apiRequest('GET', '/metrics', secret: null);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('flowcrafter_info', (string) $response->getContent());
    }

    public function testFlowsEndpointReturnsItems(): void
    {
        $flowListEntity = new FlowListEntity(
            flowHash: 'abc-123',
            flowType: 'flow.test.v1',
            flowSource: 'TestWorkflow',
            flowSubject: 'subject-1',
            flowTime: new DateTimeImmutable('2026-01-01T00:00:00.000+00:00'),
            lastTerm: new DateTimeImmutable('2026-01-01T00:00:00.000+00:00'),
            statusEnum: StatusEnum::OK,
        );

        $this->storage->method('findAllFlows')->willReturn(new ArrayIterator([$flowListEntity]));
        $this->storage->method('countFlows')->willReturn(1);

        $response = $this->apiRequest('GET', '/api/flow/flow-list');
        $this->assertSame(200, $response->getStatusCode());

        $data = $this->jsonDecode($response);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('hasMore', $data);
        $this->assertCount(1, $data['items']);
        $this->assertSame(1, $data['total']);
        $this->assertFalse($data['hasMore']);
        $this->assertSame('abc-123', $data['items'][0]['flowHash']);
        $this->assertSame('OK', $data['items'][0]['status']);
    }

    public function testFlowsDetailReturns400WhenNoParam(): void
    {
        $response = $this->apiRequest('GET', '/api/flow/flow-details');
        $this->assertSame(400, $response->getStatusCode());

        $data = $this->jsonDecode($response);
        $this->assertSame('hash or runtimeHash parameter required', $data['error']);
    }

    public function testFlowsDetailReturns404WhenNotFound(): void
    {
        $this->storage->method('findFlowByHash')->willReturn(null);

        $response = $this->apiRequest('GET', '/api/flow/flow-details', query: [
            'hash' => 'nonexistent',
        ]);
        $this->assertSame(404, $response->getStatusCode());

        $data = $this->jsonDecode($response);
        $this->assertSame('Flow not found', $data['error']);
    }

    public function testQueueCountEndpoint(): void
    {
        $this->queue->method('openQueues')->willReturn(5);

        $response = $this->apiRequest('GET', '/api/queue/queue-count');
        $this->assertSame(200, $response->getStatusCode());

        $data = $this->jsonDecode($response);
        $this->assertSame(5, $data['count']);
    }

    public function testFlowsSearchEmptySubject(): void
    {
        $response = $this->apiRequest('GET', '/api/flow/flow-search', query: [
            'subject' => '',
        ]);
        $this->assertSame(200, $response->getStatusCode());

        $data = $this->jsonDecode($response);
        $this->assertSame([], $data['items']);
        $this->assertSame(0, $data['total']);
        $this->assertFalse($data['hasMore']);
    }

    public function testExceptionsEndpointReturnsItems(): void
    {
        $this->storage->method('findAllExceptions')->willReturn(new ArrayIterator([]));
        $this->storage->method('countExceptions')->willReturn(0);

        $response = $this->apiRequest('GET', '/api/flow/exception-list');
        $this->assertSame(200, $response->getStatusCode());

        $data = $this->jsonDecode($response);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('hasMore', $data);
    }

    public function testQueueEndpointRejectsInvalidJson(): void
    {
        $response = $this->apiRequest('POST', '/api/queue/enqueue', body: 'not-json');
        $this->assertSame(400, $response->getStatusCode());

        $data = $this->jsonDecode($response);
        $this->assertSame('Invalid JSON body', $data['error']);
    }

    public function testFlowsRunEndpointRejectsMissingFields(): void
    {
        $response = $this->apiRequest('POST', '/api/flow/flow-run', body: json_encode([
            'flowHash' => '',
            'messageSource' => '',
            'message' => [],
        ], JSON_THROW_ON_ERROR));
        $this->assertSame(400, $response->getStatusCode());

        $data = $this->jsonDecode($response);
        $this->assertSame('flowHash, messageSource and message required', $data['error']);
    }

    public function testFlowsRunEndpointRejectsInvalidJson(): void
    {
        $response = $this->apiRequest('POST', '/api/flow/flow-run', body: 'not-json');
        $this->assertSame(400, $response->getStatusCode());

        $data = $this->jsonDecode($response);
        $this->assertSame('Invalid JSON body', $data['error']);
    }

    public function testQueueEndpointRejectsMissingFields(): void
    {
        $response = $this->apiRequest('POST', '/api/queue/enqueue', body: json_encode([
            'type' => '',
            'flowSource' => '',
            'messageSource' => '',
            'message' => [],
        ], JSON_THROW_ON_ERROR));
        $this->assertSame(400, $response->getStatusCode());

        $data = $this->jsonDecode($response);
        $this->assertSame('type, flowSource, messageSource and message required', $data['error']);
    }
}
