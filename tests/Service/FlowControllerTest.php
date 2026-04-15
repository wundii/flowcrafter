<?php

declare(strict_types=1);

namespace Tests\Service;

use ArrayIterator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Enum\StatusEnum;
use Wundii\Flowcrafter\FlowPreflight;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\Entity\FlowListEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowStatsEntity;
use Wundii\Flowcrafter\Storage\Entity\FlowTypeStatsEntity;
use Wundii\Service\Controller\FlowController;

final class FlowControllerTest extends TestCase
{
    public function testListReturnsItemsWithPagination(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findAllFlows')->willReturn(new ArrayIterator([$this->makeFlowListEntity()]));
        $storage->method('countFlows')->willReturn(1);

        $jsonResponse = $this->makeController($storage)->list(Request::create('/api/flows'));

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data['items']);
        $this->assertSame(1, $data['total']);
        $this->assertFalse($data['hasMore']);
    }

    public function testListSetsHasMoreWhenResultExceedsTop(): void
    {
        $items = array_map(
            fn (int $i): \Wundii\Flowcrafter\Storage\Entity\FlowListEntity => $this->makeFlowListEntity('hash-' . $i),
            range(1, 11),
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findAllFlows')->willReturn(new ArrayIterator($items));
        $storage->method('countFlows')->willReturn(11);

        $request = Request::create('/api/flows', 'GET', [
            'top' => '10',
        ]);
        $jsonResponse = $this->makeController($storage)->list($request);

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertTrue($data['hasMore']);
        $this->assertCount(10, $data['items']);
    }

    public function testListByTypeUsesTypeFilter(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('findFlowsByType')
            ->with('flow.test.v1')
            ->willReturn(new ArrayIterator([]));
        $storage->method('countFlowsByType')->willReturn(0);

        $request = Request::create('/api/flows', 'GET', [
            'type' => 'flow.test.v1',
        ]);
        $jsonResponse = $this->makeController($storage)->list($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());
    }

    public function testSearchReturnsEmptyForBlankSubject(): void
    {
        $storage = $this->createStub(StorageInterface::class);

        $request = Request::create('/api/flows/search', 'GET', [
            'subject' => '',
        ]);
        $jsonResponse = $this->makeController($storage)->search($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame([], $data['items']);
        $this->assertSame(0, $data['total']);
        $this->assertFalse($data['hasMore']);
    }

    public function testSearchReturnsMatchingFlows(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findFlowsBySubject')->willReturn(new ArrayIterator([$this->makeFlowListEntity()]));
        $storage->method('countFlowsBySubject')->willReturn(1);

        $request = Request::create('/api/flows/search', 'GET', [
            'subject' => 'subject-1',
        ]);
        $jsonResponse = $this->makeController($storage)->search($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data['items']);
        $this->assertSame(1, $data['total']);
    }

    public function testDetailReturns400WhenNoParam(): void
    {
        $storage = $this->createStub(StorageInterface::class);

        $jsonResponse = $this->makeController($storage)->detail(Request::create('/api/flows/detail'));

        $this->assertSame(400, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('hash or runtimeHash parameter required', $data['error']);
    }

    public function testDetailReturns404WhenNotFound(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findFlowByHash')->willReturn(null);

        $request = Request::create('/api/flows/detail', 'GET', [
            'hash' => 'unknown',
        ]);
        $jsonResponse = $this->makeController($storage)->detail($request);

        $this->assertSame(404, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Flow not found', $data['error']);
    }

    public function testStatsReturnsArray(): void
    {
        $flowStatsEntity = new FlowStatsEntity(date: '2026-01-01', instances: 10, runs: 25);

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findFlowStats')->willReturn(new ArrayIterator([$flowStatsEntity]));

        $jsonResponse = $this->makeController($storage)->stats(Request::create('/api/flows/stats'));

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('2026-01-01', $data[0]['date']);
        $this->assertSame(10, $data[0]['instances']);
        $this->assertSame(25, $data[0]['runs']);
    }

    public function testTypesReturnsStatsWithGroupField(): void
    {
        $flowTypeStatsEntity = new FlowTypeStatsEntity(
            prefix: 'test',
            flowType: 'flow.test.v1',
            total: 5,
            failed: 1,
            successRate: 80,
            lastTime: new DateTimeImmutable('2026-01-01T00:00:00.000+00:00'),
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findFlowTypeStats')->willReturn(new ArrayIterator([$flowTypeStatsEntity]));

        $jsonResponse = $this->makeController($storage)->types(Request::create('/api/flows/types'));

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('flow.test.v1', $data[0]['flowType']);
        $this->assertArrayHasKey('group', $data[0]);
    }

    private function makeFlowListEntity(string $flowHash = 'hash-1'): FlowListEntity
    {
        return new FlowListEntity(
            flowHash: $flowHash,
            flowType: 'flow.test.v1',
            flowSource: 'TestFlow',
            flowSubject: 'subject-1',
            flowTime: new DateTimeImmutable('2026-01-01T00:00:00.000+00:00'),
            lastTerm: new DateTimeImmutable('2026-01-01T00:00:00.000+00:00'),
            statusEnum: StatusEnum::OK,
        );
    }

    private function makeController(StorageInterface $storage): FlowController
    {
        return new FlowController(new FlowcrafterConfig(), $storage, new FlowPreflight());
    }
}
