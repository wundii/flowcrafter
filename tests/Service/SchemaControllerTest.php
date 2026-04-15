<?php

declare(strict_types=1);

namespace Tests\Service;

use ArrayIterator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Tests\MockClass\StubMock;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\Entity\FlowSchemaEntity;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;
use Wundii\Service\Controller\SchemaController;

final class SchemaControllerTest extends TestCase
{
    public function testListReturnsSchemas(): void
    {
        $flowSchemaEntity = new FlowSchemaEntity(
            schemaHash: 'hash-abc',
            type: 'flow.test.v1',
            stubs: ['StubA', 'StubB'],
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findAllSchemas')->willReturn(new ArrayIterator([$flowSchemaEntity]));

        $schemaController = new SchemaController($storage);
        $jsonResponse = $schemaController->list();

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('hash-abc', $data[0]['schemaHash']);
        $this->assertSame('flow.test.v1', $data[0]['type']);
    }

    public function testStubSourceReturnsCurrent(): void
    {
        $storage = $this->createStub(StorageInterface::class);
        $schemaController = new SchemaController($storage);

        $request = Request::create('/api/schema/stub-source', 'GET', [
            'className' => StubMock::class,
        ]);

        $jsonResponse = $schemaController->stubSource($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertTrue($data['current']);
        $this->assertStringContainsString('class StubMock', (string) $data['source']);
    }

    public function testStubSourceReturns400ForNonStubClass(): void
    {
        $storage = $this->createStub(StorageInterface::class);
        $schemaController = new SchemaController($storage);

        $request = Request::create('/api/schema/stub-source', 'GET', [
            'className' => stdClass::class,
        ]);

        $jsonResponse = $schemaController->stubSource($request);

        $this->assertSame(400, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertStringContainsString('StubInterface', (string) $data['error']);
    }

    public function testStubSourceReturns404ForUnknownHash(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findStubSourceByHash')->willReturn(null);

        $schemaController = new SchemaController($storage);

        $request = Request::create('/api/schema/stub-source', 'GET', [
            'stubHash' => 'nonexistent-hash',
        ]);

        $jsonResponse = $schemaController->stubSource($request);

        $this->assertSame(404, $jsonResponse->getStatusCode());
    }

    public function testStubSourceByHashReturnsEntity(): void
    {
        $stubSourceEntity = new StubSourceEntity(
            stubHash: 'hash-xyz',
            stubSource: StubMock::class,
            sourceContent: '<?php // source',
            time: new DateTimeImmutable('2026-01-01T00:00:00.000+00:00'),
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findStubSourceByHash')->willReturn($stubSourceEntity);

        $schemaController = new SchemaController($storage);

        $request = Request::create('/api/schema/stub-source', 'GET', [
            'stubHash' => 'hash-xyz',
        ]);

        $jsonResponse = $schemaController->stubSource($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('current', $data);
        $this->assertArrayHasKey('source', $data);
    }

    public function testStubSourcesReturnsVersionList(): void
    {
        $stubSourceEntity = new StubSourceEntity(
            stubHash: 'hash-1',
            stubSource: StubMock::class,
            sourceContent: '<?php // v1',
            time: new DateTimeImmutable('2026-01-01T00:00:00.000+00:00'),
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findStubSourcesByStubSource')->willReturn(new ArrayIterator([$stubSourceEntity]));

        $schemaController = new SchemaController($storage);

        $request = Request::create('/api/schema/stub-sources', 'GET', [
            'stubSource' => StubMock::class,
        ]);

        $jsonResponse = $schemaController->stubSources($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('current', $data[0]);
        $this->assertArrayHasKey('source', $data[0]);
        $this->assertArrayHasKey('time', $data[0]);
    }
}
