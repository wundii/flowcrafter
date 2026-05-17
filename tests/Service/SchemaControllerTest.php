<?php

declare(strict_types=1);

namespace Tests\Service;

use ArrayIterator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Tests\MockClass\StepMock;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\Entity\FlowSchemaEntity;
use Wundii\Flowcrafter\Storage\Entity\StepSourceEntity;
use Wundii\Service\Controller\SchemaController;

final class SchemaControllerTest extends TestCase
{
    public function testListReturnsSchemas(): void
    {
        $flowSchemaEntity = new FlowSchemaEntity(
            schemaHash: 'hash-abc',
            type: 'flow.test.v1',
            steps: ['StepA', 'StepB'],
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

    public function testStepSourceReturnsCurrent(): void
    {
        $storage = $this->createStub(StorageInterface::class);
        $schemaController = new SchemaController($storage);

        $request = Request::create('/api/flow/step-source', 'GET', [
            'className' => StepMock::class,
        ]);

        $jsonResponse = $schemaController->stepSource($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertTrue($data['current']);
        $this->assertStringContainsString('class StepMock', (string) $data['source']);
    }

    public function testStepSourceReturns400ForNonStepClass(): void
    {
        $storage = $this->createStub(StorageInterface::class);
        $schemaController = new SchemaController($storage);

        $request = Request::create('/api/flow/step-source', 'GET', [
            'className' => stdClass::class,
        ]);

        $jsonResponse = $schemaController->stepSource($request);

        $this->assertSame(400, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertStringContainsString('StepInterface', (string) $data['error']);
    }

    public function testStepSourceReturns404ForUnknownHash(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findStepSourceByHash')->willReturn(null);

        $schemaController = new SchemaController($storage);

        $request = Request::create('/api/flow/step-source', 'GET', [
            'stepHash' => 'nonexistent-hash',
        ]);

        $jsonResponse = $schemaController->stepSource($request);

        $this->assertSame(404, $jsonResponse->getStatusCode());
    }

    public function testStepSourceByHashReturnsEntity(): void
    {
        $stepSourceEntity = new StepSourceEntity(
            stepHash: 'hash-xyz',
            stepSource: StepMock::class,
            sourceContent: '<?php // source',
            time: new DateTimeImmutable('2026-01-01T00:00:00.000+00:00'),
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findStepSourceByHash')->willReturn($stepSourceEntity);

        $schemaController = new SchemaController($storage);

        $request = Request::create('/api/flow/step-source', 'GET', [
            'stepHash' => 'hash-xyz',
        ]);

        $jsonResponse = $schemaController->stepSource($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('current', $data);
        $this->assertArrayHasKey('source', $data);
    }

    public function testStepSourcesReturnsVersionList(): void
    {
        $stepSourceEntity = new StepSourceEntity(
            stepHash: 'hash-1',
            stepSource: StepMock::class,
            sourceContent: '<?php // v1',
            time: new DateTimeImmutable('2026-01-01T00:00:00.000+00:00'),
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findStepSourcesByStepSource')->willReturn(new ArrayIterator([$stepSourceEntity]));

        $schemaController = new SchemaController($storage);

        $request = Request::create('/api/flow/step-source-list', 'GET', [
            'stepSource' => StepMock::class,
        ]);

        $jsonResponse = $schemaController->stepSources($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('current', $data[0]);
        $this->assertArrayHasKey('source', $data[0]);
        $this->assertArrayHasKey('time', $data[0]);
    }
}
