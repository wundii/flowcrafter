<?php

declare(strict_types=1);

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use Wundii\Service\Controller\HealthController;

final class HealthControllerTest extends TestCase
{
    private HealthController $healthController;

    protected function setUp(): void
    {
        $this->healthController = new HealthController();
    }

    public function testIndexReturnsOk(): void
    {
        $jsonResponse = $this->healthController->index();

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('ok', $data['status']);
    }

    public function testPingReturnsPong(): void
    {
        $jsonResponse = $this->healthController->ping();

        $this->assertSame(200, $jsonResponse->getStatusCode());
        $this->assertSame('"pong"', $jsonResponse->getContent());
    }
}
