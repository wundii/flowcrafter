<?php

declare(strict_types=1);

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Service\Controller\InfoController;

final class InfoControllerTest extends TestCase
{
    public function testInfoReturnsExpectedKeys(): void
    {
        $jsonResponse = $this->makeController()->info();

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('php', $data);
        $this->assertArrayHasKey('storage', $data);
        $this->assertArrayHasKey('workers', $data);
        $this->assertArrayHasKey('scheduler', $data);
    }

    public function testInfoReturnsDescription(): void
    {
        $jsonResponse = $this->makeController('My Test Instance')->info();

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('My Test Instance', $data['description']);
    }

    public function testMetricsReturnsPrometheusFormat(): void
    {
        $response = $this->makeController('Test')->metrics();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();
        $this->assertStringContainsString('flowcrafter_info', $body);
        $this->assertStringContainsString('flowcrafter_queue_size', $body);
        $this->assertStringContainsString('flowcrafter_flows_total', $body);
        $this->assertStringContainsString('flowcrafter_exceptions_7d', $body);
        $this->assertStringContainsString('flowcrafter_observer_up', $body);
        $this->assertStringContainsString('flowcrafter_scheduler_up', $body);
    }

    public function testMetricsContainsDescriptionLabel(): void
    {
        $response = $this->makeController('My Description')->metrics();

        $body = (string) $response->getContent();
        $this->assertStringContainsString('My Description', $body);
    }

    private function makeController(?string $description = null): InfoController
    {
        $flowcrafterConfig = new FlowcrafterConfig();
        if ($description !== null) {
            $flowcrafterConfig->setServerDescription($description);
        }

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('countFlows')->willReturn(0);
        $storage->method('countExceptions')->willReturn(0);
        $storage->method('countScheduleExceptions')->willReturn(0);

        $queue = $this->createMock(QueueInterface::class);
        $queue->method('openQueues')->willReturn(0);

        return new InfoController($flowcrafterConfig, $storage, $queue);
    }
}
