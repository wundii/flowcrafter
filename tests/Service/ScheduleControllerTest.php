<?php

declare(strict_types=1);

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Tests\MockClass\ScheduleMock;
use Wundii\Service\Controller\ScheduleController;

final class ScheduleControllerTest extends TestCase
{
    public function testListReturnsJsonArray(): void
    {
        $scheduleController = new ScheduleController();
        $jsonResponse = $scheduleController->list();

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testSourceReturnsPhpCode(): void
    {
        $scheduleController = new ScheduleController();
        $request = Request::create('/api/schedule/source', 'GET', [
            'className' => ScheduleMock::class,
        ]);

        $jsonResponse = $scheduleController->source($request);

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('source', $data);
        $this->assertArrayHasKey('className', $data);
        $this->assertStringContainsString('class ScheduleMock', (string) $data['source']);
    }

    public function testSourceReturns404ForUnknownClass(): void
    {
        $scheduleController = new ScheduleController();
        $request = Request::create('/api/schedule/source', 'GET', [
            'className' => 'NonExistent\\ClassName',
        ]);

        $jsonResponse = $scheduleController->source($request);

        $this->assertSame(404, $jsonResponse->getStatusCode());
    }

    public function testSourceReturns400ForNonScheduleClass(): void
    {
        $scheduleController = new ScheduleController();
        $request = Request::create('/api/schedule/source', 'GET', [
            'className' => stdClass::class,
        ]);

        $jsonResponse = $scheduleController->source($request);

        $this->assertSame(400, $jsonResponse->getStatusCode());
        $this->assertStringContainsString('AbstractSchedule', (string) $jsonResponse->getContent());
    }
}
