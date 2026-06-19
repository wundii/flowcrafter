<?php

declare(strict_types=1);

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Service\CaddyfileBuilder;

final class CaddyfileBuilderTest extends TestCase
{
    public function testDefaultNumThreadsDerivedFromWorkers(): void
    {
        $caddyfile = $this->builder(workers: 4)->build();

        $this->assertStringContainsString('num_threads 8', $caddyfile);
    }

    public function testExplicitNumThreadsOverridesAutoDerive(): void
    {
        $caddyfile = $this->builder(workers: 4, numThreads: 12)->build();

        $this->assertStringContainsString('num_threads 12', $caddyfile);
        $this->assertStringNotContainsString('num_threads 8', $caddyfile);
    }

    public function testHttpsEmitsHttpsScheme(): void
    {
        $caddyfile = $this->builder(https: true)->build();

        $this->assertStringContainsString('https://:8000 {', $caddyfile);
        $this->assertStringNotContainsString('auto_https off', $caddyfile);
    }

    public function testHttpEmitsAutoHttpsOff(): void
    {
        $caddyfile = $this->builder(https: false)->build();

        $this->assertStringContainsString('http://:8000 {', $caddyfile);
        $this->assertStringContainsString('auto_https off', $caddyfile);
    }

    public function testProductionDefaultsPresent(): void
    {
        $caddyfile = $this->builder()->build();

        $this->assertStringContainsString('admin off', $caddyfile);
        $this->assertStringContainsString('encode zstd gzip', $caddyfile);
        $this->assertStringContainsString('read_header 5s', $caddyfile);
        $this->assertStringContainsString('format json', $caddyfile);
    }

    public function testWorkerDirectiveContainsCorrectPath(): void
    {
        $caddyfile = $this->builder(workers: 2, serviceDir: '/app/service')->build();

        $this->assertStringContainsString('worker /app/service/worker.php 2', $caddyfile);
    }

    public function testMaxThreadsIsDoubleOfNumThreads(): void
    {
        $caddyfile = $this->builder(workers: 4, numThreads: 8)->build();

        $this->assertStringContainsString('num_threads 8', $caddyfile);
        $this->assertStringContainsString('max_threads 16', $caddyfile);
    }

    public function testNumThreadsIsBumpedAboveWorkers(): void
    {
        // FrankenPHP requires num_threads > workers; builder must enforce this.
        $caddyfile = $this->builder(workers: 4, numThreads: 4)->build();

        $this->assertStringContainsString('num_threads 5', $caddyfile);
        $this->assertStringContainsString('max_threads 10', $caddyfile);
    }

    public function testNumThreadsBelowWorkersIsBumped(): void
    {
        $caddyfile = $this->builder(workers: 8, numThreads: 2)->build();

        $this->assertStringContainsString('num_threads 9', $caddyfile);
    }

    private function builder(
        string $host = '0.0.0.0',
        int $port = 8000,
        int $workers = 4,
        ?int $numThreads = null,
        bool $https = false,
        string $serviceDir = '/app/service',
    ): CaddyfileBuilder {
        return new CaddyfileBuilder(
            host: $host,
            port: $port,
            workers: $workers,
            numThreads: $numThreads,
            https: $https,
            serviceDir: $serviceDir,
        );
    }
}
