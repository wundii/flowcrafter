<?php

declare(strict_types=1);

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\MessageSubDataMock;
use Tests\MockClass\WorkflowMock;
use Wundii\Flowcrafter\FlowPreflight;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Service\Controller\QueueController;

final class QueueControllerTest extends TestCase
{
    public function testEnqueueReturns400ForNonFlowSource(): void
    {
        $queueController = $this->makeController();
        $jsonResponse = $queueController->enqueue($this->makeRequest([
            'type' => 'flow.workflow.v1',
            'flowSource' => stdClass::class,
            'messageSource' => MessageInitMock::class,
            'message' => [
                'data' => 'x',
            ],
        ]));

        $this->assertSame(400, $jsonResponse->getStatusCode());
        $this->assertStringContainsString('FlowInterface', (string) $jsonResponse->getContent());
    }

    public function testEnqueueReturns400ForNonMessageSource(): void
    {
        $queueController = $this->makeController();
        $jsonResponse = $queueController->enqueue($this->makeRequest([
            'type' => 'flow.workflow.v1',
            'flowSource' => WorkflowMock::class,
            'messageSource' => stdClass::class,
            'message' => [
                'data' => 'x',
            ],
        ]));

        $this->assertSame(400, $jsonResponse->getStatusCode());
        $this->assertStringContainsString('AbstractMessage', (string) $jsonResponse->getContent());
    }

    public function testEnqueueReturns400ForMessageNotInFlow(): void
    {
        $queueController = $this->makeController();
        $jsonResponse = $queueController->enqueue($this->makeRequest([
            'type' => 'flow.workflow.v1',
            'flowSource' => WorkflowMock::class,
            'messageSource' => MessageSubDataMock::class,
            'message' => [
                'value' => 'x',
            ],
        ]));

        $this->assertSame(400, $jsonResponse->getStatusCode());
        $this->assertStringContainsString('is not consumed by any stub', (string) $jsonResponse->getContent());
    }

    public function testEnqueueReturns400ForIncompletePayload(): void
    {
        $queueController = $this->makeController();
        $jsonResponse = $queueController->enqueue($this->makeRequest([
            'type' => 'flow.workflow.v1',
            'flowSource' => WorkflowMock::class,
            'messageSource' => MessageInitMock::class,
            'message' => [
                'unknown_key' => 'x',
            ],
        ]));

        $this->assertSame(400, $jsonResponse->getStatusCode());
        $this->assertStringContainsString('missing required keys', (string) $jsonResponse->getContent());
    }

    public function testEnqueueAppendsObserveItemOnValidInput(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->expects($this->once())
            ->method('appendObserveItem')
            ->with(
                'flow.workflow.v1',
                WorkflowMock::class,
                null,
                MessageInitMock::class,
                [
                    'data' => 'hello',
                ],
                [],
                'subject-1',
            );

        $queueController = new QueueController($storage, new FlowPreflight());
        $jsonResponse = $queueController->enqueue($this->makeRequest([
            'type' => 'flow.workflow.v1',
            'flowSource' => WorkflowMock::class,
            'messageSource' => MessageInitMock::class,
            'message' => [
                'data' => 'hello',
            ],
            'flowSubject' => 'subject-1',
        ]));

        $this->assertSame(200, $jsonResponse->getStatusCode());
        $this->assertStringContainsString('"queued":true', (string) $jsonResponse->getContent());
    }

    private function makeController(): QueueController
    {
        return new QueueController($this->createStub(StorageInterface::class), new FlowPreflight());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function makeRequest(array $body): Request
    {
        $encoded = json_encode($body);
        $this->assertIsString($encoded);

        return Request::create('/api/queue', 'POST', [], [], [], [], $encoded);
    }
}
