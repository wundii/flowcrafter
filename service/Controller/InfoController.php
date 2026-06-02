<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use DateTimeImmutable;
use DateTimeInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;

final class InfoController
{
    public function __construct(
        private readonly FlowcrafterConfig $flowcrafterConfig,
        private readonly StorageInterface $storage,
        private readonly QueueInterface $queue,
    ) {
    }

    public function info(): JsonResponse
    {
        return new JsonResponse([
            'version' => FlowConsole::vendorVersion(),
            'php' => PHP_VERSION,
            'storage' => (new ReflectionClass($this->storage))->getShortName(),
            'queue' => (new ReflectionClass($this->queue))->getShortName(),
            'description' => $this->flowcrafterConfig->getServerDescription(),
            'dev' => $this->flowcrafterConfig->getServerDev(),
            'workers' => $this->getHeartbeats('observer'),
            'scheduler' => $this->getHeartbeats('scheduler'),
            'projection' => $this->getHeartbeats('projection'),
        ]);
    }

    public function metrics(): Response
    {
        $observerWorkers = $this->getHeartbeats('observer');
        $schedulerInstances = $this->getHeartbeats('scheduler');
        $projectionWorkers = $this->getHeartbeats('projection');

        $queueSize = $this->queue->openQueues();
        $flowsTotal = $this->storage->countFlows();
        $exceptions7d = $this->storage->countExceptions(
            from: new DateTimeImmutable('-7 days'),
        );
        $scheduleExceptions7d = $this->storage->countScheduleExceptions(
            from: new DateTimeImmutable('-7 days'),
        );

        $description = $this->flowcrafterConfig->getServerDescription() ?? '';
        $safeDescription = str_replace(['"', "\n", "\r"], ['\"', ' ', ' '], $description);
        $storageType = strtolower((new ReflectionClass($this->storage))->getShortName());

        $lines = [];

        $lines[] = '# HELP flowcrafter_info FlowCrafter service information';
        $lines[] = '# TYPE flowcrafter_info gauge';
        $lines[] = sprintf('flowcrafter_info{description="%s",storage="%s"} 1', $safeDescription, $storageType);

        $lines[] = '# HELP flowcrafter_observer_up Whether the FlowCrafter observer process is running (1 = up, 0 = down)';
        $lines[] = '# TYPE flowcrafter_observer_up gauge';
        $lines[] = sprintf('flowcrafter_observer_up %d', $observerWorkers !== [] ? 1 : 0);

        $lines[] = '# HELP flowcrafter_observer_workers Number of active observer worker processes';
        $lines[] = '# TYPE flowcrafter_observer_workers gauge';
        $lines[] = sprintf('flowcrafter_observer_workers %d', count($observerWorkers));

        $lines[] = '# HELP flowcrafter_scheduler_up Whether the FlowCrafter scheduler process is running (1 = up, 0 = down)';
        $lines[] = '# TYPE flowcrafter_scheduler_up gauge';
        $lines[] = sprintf('flowcrafter_scheduler_up %d', $schedulerInstances !== [] ? 1 : 0);

        $lines[] = '# HELP flowcrafter_scheduler_workers Number of active scheduler instances';
        $lines[] = '# TYPE flowcrafter_scheduler_workers gauge';
        $lines[] = sprintf('flowcrafter_scheduler_workers %d', count($schedulerInstances));

        $lines[] = '# HELP flowcrafter_projection_up Whether the FlowCrafter projection worker is running (1 = up, 0 = down)';
        $lines[] = '# TYPE flowcrafter_projection_up gauge';
        $lines[] = sprintf('flowcrafter_projection_up %d', $projectionWorkers !== [] ? 1 : 0);

        $lines[] = '# HELP flowcrafter_projection_workers Number of active projection worker processes';
        $lines[] = '# TYPE flowcrafter_projection_workers gauge';
        $lines[] = sprintf('flowcrafter_projection_workers %d', count($projectionWorkers));

        $lines[] = '# HELP flowcrafter_queue_size Number of items currently pending in the queue';
        $lines[] = '# TYPE flowcrafter_queue_size gauge';
        $lines[] = sprintf('flowcrafter_queue_size %d', $queueSize);

        $lines[] = '# HELP flowcrafter_flows_total Total number of flow instances';
        $lines[] = '# TYPE flowcrafter_flows_total gauge';
        $lines[] = sprintf('flowcrafter_flows_total %d', $flowsTotal);

        $lines[] = '# HELP flowcrafter_exceptions_7d Number of flow exceptions in the last 7 days';
        $lines[] = '# TYPE flowcrafter_exceptions_7d gauge';
        $lines[] = sprintf('flowcrafter_exceptions_7d %d', $exceptions7d);

        $lines[] = '# HELP flowcrafter_schedule_exceptions_7d Number of schedule exceptions in the last 7 days';
        $lines[] = '# TYPE flowcrafter_schedule_exceptions_7d gauge';
        $lines[] = sprintf('flowcrafter_schedule_exceptions_7d %d', $scheduleExceptions7d);

        $lines[] = '# HELP flowcrafter_projection_exceptions_7d Number of projection exceptions in the last 7 days';
        $lines[] = '# TYPE flowcrafter_projection_exceptions_7d gauge';
        $lines[] = sprintf('flowcrafter_projection_exceptions_7d %d', $this->storage->countProjectionExceptions(
            from: new DateTimeImmutable('-7 days'),
        ));

        $body = implode("\n", $lines) . "\n";

        return new Response($body, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    /**
     * @return list<array{hostname: non-empty-string, pid: int, lastHeartbeat: string}>
     */
    private function getHeartbeats(string $type): array
    {
        $heartbeatDir = sys_get_temp_dir() . '/flowcrafter';
        $results = [];
        foreach (glob($heartbeatDir . '/' . $type . '.*.heartbeat') ?: [] as $file) {
            $mtime = filemtime($file);
            if ($mtime === false) {
                continue;
            }

            if ((time() - $mtime) > 60) {
                continue;
            }

            $baseName = basename($file, '.heartbeat');
            if (!preg_match('/^' . preg_quote($type, '/') . '\.(.+)\.(\d+)$/', $baseName, $m)) {
                continue;
            }

            $results[] = [
                'hostname' => $m[1],
                'pid' => (int) $m[2],
                'lastHeartbeat' => date(DateTimeInterface::RFC3339, $mtime),
            ];
        }

        return $results;
    }
}
