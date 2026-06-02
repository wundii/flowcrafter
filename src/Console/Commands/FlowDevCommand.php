<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Throwable;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\FileWatcher;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Heartbeat;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\Console\Preflight\StoragePreflight;
use Wundii\Flowcrafter\FlowScheduler;
use Wundii\Flowcrafter\Projection\ProjectionDiscovery;
use Wundii\Flowcrafter\Projection\ProjectionWorker;

final class FlowDevCommand extends Command
{
    private ?Process $serverProcess = null;

    private ?Process $observerProcess = null;

    private ?Heartbeat $heartbeat = null;

    private ?Heartbeat $schedulerHeartbeat = null;

    public function __construct(
        private FlowcrafterConfig $flowcrafterConfig,
        private BootstrapConfig $bootstrapConfig,
        private StoragePreflight $storagePreflight,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('dev');
        $this->setDescription('Start the API server (PHP built-in) and observer process for development');
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Server host', '0.0.0.0');
        $this->addOption('port', null, InputOption::VALUE_REQUIRED, 'Server port', '8000');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        if (!$this->storagePreflight->ensureReady($output)) {
            return Command::FAILURE;
        }

        /** @var string $host */
        $host = $input->getOption('host');
        /** @var string $port */
        $port = $input->getOption('port');
        $serviceIndex = dirname(__DIR__, 3) . '/service/index.php';

        $output->writeln(sprintf(
            '<fg=%s>starting API server (PHP built-in) on %s:%s</>',
            OutputColorEnum::BLUE->value,
            $host,
            $port,
        ));

        $env = $this->bootstrapConfig->getProcessEnv();
        if (is_array($env)) {
            $env['FLOWCRAFTER_DEV'] = '1';
        }

        $serverProcess = new Process(
            [PHP_BINARY, '-S', sprintf('%s:%s', $host, $port), $serviceIndex],
            null,
            $env,
        );
        $serverProcess->setTimeout(null);
        $serverProcess->start(function (string $type, string $data) use ($output): void {
            $output->write($data);
        });

        if (!$serverProcess->isRunning()) {
            $output->writeln('<fg=red>failed to start the API server</>');
            return Command::FAILURE;
        }

        $this->serverProcess = $serverProcess;

        register_shutdown_function(function (): void {
            $this->cleanup();
        });

        $output->writeln(sprintf(
            '<fg=%s>starting observer (subprocess)</>',
            OutputColorEnum::BLUE->value,
        ));

        $this->observerProcess = $this->startObserverProcess($env, $output);

        $this->heartbeat = new Heartbeat();

        $storage = $this->flowcrafterConfig->getStorage();
        $queue = $this->flowcrafterConfig->getQueue();
        $dependencyInjections = $this->flowcrafterConfig->getDependencyInjections();
        $projectionHandlerMetas = ProjectionDiscovery::discover();

        $flowScheduler = new FlowScheduler($storage, $queue, $dependencyInjections, projectionHandlerMetas: $projectionHandlerMetas);
        $scheduleCount = count($flowScheduler->getScheduleAttributes());
        if ($scheduleCount > 0 && $output->confirm(sprintf('Start scheduler? (%d schedule(s) found)', $scheduleCount), false)) {
            $output->writeln(sprintf(
                '<fg=%s>starting scheduler with %d schedule(s)</>',
                OutputColorEnum::BLUE->value,
                $scheduleCount,
            ));
            $this->schedulerHeartbeat = new Heartbeat('scheduler');
            $this->schedulerHeartbeat->touch();
        }

        $projectionWorker = null;
        if ($projectionHandlerMetas !== [] && $output->confirm(sprintf('Start projection worker? (%d handler(s) found)', count($projectionHandlerMetas)), false)) {
            $projectionWorker = new ProjectionWorker($storage, $queue, $projectionHandlerMetas, $dependencyInjections);
            $output->writeln(sprintf(
                '<fg=%s>starting projection worker with %d handler(s)</>',
                OutputColorEnum::BLUE->value,
                count($projectionHandlerMetas),
            ));
        }

        $fileWatcher = new FileWatcher(FileWatcher::resolveProjectDirectories());

        $output->writeln('');

        $logger = static function (string $message) use ($output): void {
            $output->writeln($message);
        };

        $this->heartbeat->touch();

        /** @phpstan-ignore while.alwaysTrue */
        while ($serverProcess->isRunning()) {
            if ($fileWatcher->hasChanges()) {
                $output->writeln(sprintf(
                    '<fg=%s>file changes detected, restarting observer...</>',
                    OutputColorEnum::YELLOW->value,
                ));
                $this->observerProcess->stop();
                $this->observerProcess = $this->startObserverProcess($env, $output);
                $fileWatcher->reset();
            }

            if (!$this->observerProcess->isRunning()) {
                $output->writeln(sprintf(
                    '<fg=%s>observer stopped, restarting...</>',
                    OutputColorEnum::YELLOW->value,
                ));
                $this->observerProcess = $this->startObserverProcess($env, $output);
            }

            if ($this->schedulerHeartbeat instanceof Heartbeat) {
                try {
                    $flowScheduler->tick($logger);
                } catch (Throwable $e) {
                    $output->writeln('[Scheduler] error: ' . $e->getMessage());
                }
            }

            if ($projectionWorker instanceof ProjectionWorker) {
                try {
                    $projectionWorker->tick($logger);
                } catch (Throwable $e) {
                    $output->writeln('[Projection] error: ' . $e->getMessage());
                }
            }

            $this->heartbeat->touch();
            $this->schedulerHeartbeat?->touch();

            sleep(1);
        }

        /** @phpstan-ignore deadCode.unreachable */
        $output->writeln('<fg=red>API server stopped unexpectedly</>');

        return Command::FAILURE;
    }

    /**
     * @param array<string, string>|null $env
     */
    private function startObserverProcess(?array $env, FlowSymfonyStyle $flowSymfonyStyle): Process
    {
        $flowcrafterScript = dirname(__DIR__, 3) . '/bin/flowcrafter.php';

        $process = new Process(
            [PHP_BINARY, $flowcrafterScript, 'observer', '--workers', '1'],
            null,
            $env,
        );
        $process->setTimeout(null);
        $process->start(function (string $type, string $data) use ($flowSymfonyStyle): void {
            $flowSymfonyStyle->write($data);
        });

        return $process;
    }

    private function cleanup(): void
    {
        if ($this->observerProcess?->isRunning()) {
            $this->observerProcess->stop();
        }

        if ($this->serverProcess?->isRunning()) {
            $this->serverProcess->stop();
        }

        $this->heartbeat?->cleanup();
        $this->schedulerHeartbeat?->cleanup();
    }
}
