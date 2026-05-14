<?php

declare(strict_types=1);

namespace Tests\Service;

use ArrayIterator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\Entity\ExceptionListEntity;
use Wundii\Flowcrafter\Storage\Entity\ExceptionStatsEntity;
use Wundii\Service\Controller\ExceptionController;

final class ExceptionControllerTest extends TestCase
{
    public function testListReturnsEmptyResult(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findAllExceptions')->willReturn(new ArrayIterator([]));
        $storage->method('countExceptions')->willReturn(0);
        $storage->method('countScheduleExceptions')->willReturn(0);

        $exceptionController = new ExceptionController($storage);
        $jsonResponse = $exceptionController->list(Request::create('/api/flow/exception-list'));

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame([], $data['items']);
        $this->assertSame(0, $data['total']);
        $this->assertFalse($data['hasMore']);
    }

    public function testListReturnsPaginatedItems(): void
    {
        $exceptionListEntity = new ExceptionListEntity(
            type: 'flow',
            hash: 'exc-hash-1',
            code: 500,
            message: 'Something went wrong',
            file: '/var/www/SomeStep.php',
            line: 42,
            traceString: '',
            time: new DateTimeImmutable('2026-01-01T00:00:00.000+00:00'),
            flowHash: 'flow-hash-1',
            flowRuntimeHash: 'runtime-hash-1',
            flowType: 'flow.test.v1',
            stepSource: 'SomeStep',
            stepHash: 'step-hash-1',
            flowStatus: null,
            scheduleClass: null,
            scheduleName: null,
            scheduleExpression: null,
        );

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findAllExceptions')->willReturn(new ArrayIterator([$exceptionListEntity]));
        $storage->method('countExceptions')->willReturn(1);
        $storage->method('countScheduleExceptions')->willReturn(0);

        $exceptionController = new ExceptionController($storage);
        $jsonResponse = $exceptionController->list(Request::create('/api/flow/exception-list'));

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data['items']);
        $this->assertSame(1, $data['total']);
        $this->assertFalse($data['hasMore']);
        $this->assertSame('exc-hash-1', $data['items'][0]['hash']);
        $this->assertSame('flow', $data['items'][0]['type']);
    }

    public function testListCombinesFlowAndScheduleExceptionCounts(): void
    {
        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findAllExceptions')->willReturn(new ArrayIterator([]));
        $storage->method('countExceptions')->willReturn(3);
        $storage->method('countScheduleExceptions')->willReturn(2);

        $exceptionController = new ExceptionController($storage);
        $jsonResponse = $exceptionController->list(Request::create('/api/flow/exception-list'));

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame(5, $data['total']);
    }

    public function testStatsReturnsArray(): void
    {
        $exceptionStatsEntity = new ExceptionStatsEntity(date: '2026-01-01', flow: 3, schedule: 1);

        $storage = $this->createMock(StorageInterface::class);
        $storage->method('findExceptionStats')->willReturn(new ArrayIterator([$exceptionStatsEntity]));

        $exceptionController = new ExceptionController($storage);
        $jsonResponse = $exceptionController->stats(Request::create('/api/flow/exceptions-stats'));

        $this->assertSame(200, $jsonResponse->getStatusCode());

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('2026-01-01', $data[0]['date']);
        $this->assertSame(3, $data[0]['flow']);
        $this->assertSame(1, $data[0]['schedule']);
    }
}
