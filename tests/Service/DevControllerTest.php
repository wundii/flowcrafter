<?php

declare(strict_types=1);

namespace Tests\Service;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Service\Controller\DevController;

final class DevControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('FLOWCRAFTER_DEV');
    }

    public function testFlowsReturns403WhenDevModeDisabled(): void
    {
        $jsonResponse = $this->makeController(false)->flows();

        $this->assertSame(403, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Dev mode is not enabled', $data['error']);
    }

    public function testFlowReturns403WhenDevModeDisabled(): void
    {
        $request = Request::create('/dev/flow', 'GET', [
            'className' => WorkflowMock::class,
        ]);
        $jsonResponse = $this->makeController(false)->flow($request);

        $this->assertSame(403, $jsonResponse->getStatusCode());
    }

    public function testRunReturns403WhenDevModeDisabled(): void
    {
        $request = Request::create('/dev/run', 'POST', [], [], [], [], json_encode([
            'className' => WorkflowMock::class,
            'messageSource' => MessageInitMock::class,
            'message' => [
                'data' => 'x',
            ],
        ], JSON_THROW_ON_ERROR));

        $jsonResponse = $this->makeController(false)->run($request);

        $this->assertSame(403, $jsonResponse->getStatusCode());
    }

    public function testFlowsReturnsArrayInDevMode(): void
    {
        $jsonResponse = $this->makeController()->flows();

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testFlowReturns400WhenClassNameMissing(): void
    {
        $request = Request::create('/dev/flow', 'GET', [
            'className' => '',
        ]);
        $jsonResponse = $this->makeController()->flow($request);

        $this->assertSame(400, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('className is required', $data['error']);
    }

    public function testFlowReturns404ForUnknownClass(): void
    {
        $request = Request::create('/dev/flow', 'GET', [
            'className' => 'NonExistent\\Flow',
        ]);
        $jsonResponse = $this->makeController()->flow($request);

        $this->assertSame(404, $jsonResponse->getStatusCode());
    }

    public function testFlowReturns400ForNonFlowInterfaceClass(): void
    {
        $request = Request::create('/dev/flow', 'GET', [
            'className' => stdClass::class,
        ]);
        $jsonResponse = $this->makeController()->flow($request);

        $this->assertSame(400, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertStringContainsString('FlowInterface', (string) $data['error']);
    }

    public function testFlowReturnsSchemaForValidClass(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findAllSchemas')->willReturn(new ArrayIterator([]));
        $storage->method('findMessageSourceByMessageSource')->willReturn(new ArrayIterator([]));

        putenv('FLOWCRAFTER_DEV=1');
        $devController = new DevController(new FlowcrafterConfig(), $storage);
        $request = Request::create('/dev/flow', 'GET', [
            'className' => WorkflowMock::class,
        ]);
        $jsonResponse = $devController->flow($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertTrue($data['valid']);
        $this->assertNull($data['error']);
        $this->assertArrayHasKey('schema', $data);
        $this->assertArrayHasKey('hash', $data);
        $this->assertArrayHasKey('hashDrift', $data);
        $this->assertArrayHasKey('initMessageSchema', $data);
    }

    public function testRunReturns400ForInvalidJson(): void
    {
        $request = Request::create('/dev/run', 'POST', [], [], [], [], 'not-json');
        $jsonResponse = $this->makeController()->run($request);

        $this->assertSame(400, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid JSON body', $data['error']);
    }

    public function testRunReturns400WhenFieldsMissing(): void
    {
        $request = Request::create('/dev/run', 'POST', [], [], [], [], json_encode([
            'className' => '',
            'messageSource' => '',
            'message' => [],
        ], JSON_THROW_ON_ERROR));

        $jsonResponse = $this->makeController()->run($request);

        $this->assertSame(400, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('className and messageSource required', $data['error']);
    }

    public function testRunReturns404ForUnknownClass(): void
    {
        $request = Request::create('/dev/run', 'POST', [], [], [], [], json_encode([
            'className' => 'NonExistent\\Flow',
            'messageSource' => MessageInitMock::class,
            'message' => [
                'data' => 'x',
            ],
        ], JSON_THROW_ON_ERROR));

        $jsonResponse = $this->makeController()->run($request);

        $this->assertSame(404, $jsonResponse->getStatusCode());
    }

    public function testRunReturns400ForNonFlowInterfaceClass(): void
    {
        $request = Request::create('/dev/run', 'POST', [], [], [], [], json_encode([
            'className' => stdClass::class,
            'messageSource' => MessageInitMock::class,
            'message' => [
                'data' => 'x',
            ],
        ], JSON_THROW_ON_ERROR));

        $jsonResponse = $this->makeController()->run($request);

        $this->assertSame(400, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertStringContainsString('FlowInterface', (string) $data['error']);
    }

    public function testRunExecutesFlowAndReturnsResult(): void
    {
        $request = Request::create('/dev/run', 'POST', [], [], [], [], json_encode([
            'className' => WorkflowMock::class,
            'messageSource' => MessageInitMock::class,
            'message' => [
                'data' => 'test-input',
            ],
        ], JSON_THROW_ON_ERROR));

        $jsonResponse = $this->makeController()->run($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('flow', $data);
        $this->assertArrayHasKey('messageReturn', $data);
        $this->assertArrayHasKey('memory', $data);
        $this->assertIsArray($data['memory']);
        $this->assertArrayHasKey('used', $data['memory']);
        $this->assertArrayHasKey('peak', $data['memory']);
        $this->assertIsInt($data['memory']['used']);
        $this->assertIsInt($data['memory']['peak']);
        $this->assertGreaterThan(0, $data['memory']['peak']);
    }

    private function makeController(bool $devMode = true): DevController
    {
        putenv($devMode ? 'FLOWCRAFTER_DEV=1' : 'FLOWCRAFTER_DEV=0');

        $storage = $this->createStep(StorageInterface::class);

        return new DevController(new FlowcrafterConfig(), $storage);
    }
}
