<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Console\FileWatcher;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Heartbeat;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\Console\Preflight\StoragePreflight;
use Wundii\Flowcrafter\Projection\ProjectionDiscovery;
use Wundii\Flowcrafter\Schedule\ScheduleDiscovery;

final class FlowDevCommand extends Command
{
    private ?Process $serverProcess = null;

    private ?Process $observerProcess = null;

    private ?Process $projectionWorkerProcess = null;

    private ?Process $schedulerProcess = null;

    private ?Heartbeat $heartbeat = null;

    public function __construct(
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

        $projectionHandlerMetas = ProjectionDiscovery::discover();

        $scheduleCount = count(ScheduleDiscovery::discover());
        if ($scheduleCount > 0 && $output->confirm(sprintf('Start scheduler? (%d schedule(s) found)', $scheduleCount), false)) {
            $output->writeln(sprintf(
                '<fg=%s>starting scheduler (subprocess) with %d schedule(s)</>',
                OutputColorEnum::BLUE->value,
                $scheduleCount,
            ));
            $this->schedulerProcess = $this->startSchedulerProcess($env, $output);
        }

        if ($projectionHandlerMetas !== [] && $output->confirm(sprintf('Start projection worker? (%d handler(s) found)', count($projectionHandlerMetas)), false)) {
            $output->writeln(sprintf(
                '<fg=%s>starting projection worker (subprocess) with %d handler(s)</>',
                OutputColorEnum::BLUE->value,
                count($projectionHandlerMetas),
            ));
            $this->projectionWorkerProcess = $this->startProjectionWorkerProcess($env, $output);
        }

        $fileWatcher = new FileWatcher(FileWatcher::resolveProjectDirectories());

        $output->writeln('');

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

                if ($this->projectionWorkerProcess instanceof Process) {
                    $output->writeln(sprintf(
                        '<fg=%s>file changes detected, restarting projection worker...</>',
                        OutputColorEnum::YELLOW->value,
                    ));
                    $this->projectionWorkerProcess->stop();
                    $this->projectionWorkerProcess = $this->startProjectionWorkerProcess($env, $output);
                }

                if ($this->schedulerProcess instanceof Process) {
                    $output->writeln(sprintf(
                        '<fg=%s>file changes detected, restarting scheduler...</>',
                        OutputColorEnum::YELLOW->value,
                    ));
                    $this->schedulerProcess->stop();
                    $this->schedulerProcess = $this->startSchedulerProcess($env, $output);
                }

                $fileWatcher->reset();
            }

            if (!$this->observerProcess->isRunning()) {
                $output->writeln(sprintf(
                    '<fg=%s>observer stopped, restarting...</>',
                    OutputColorEnum::YELLOW->value,
                ));
                $this->observerProcess = $this->startObserverProcess($env, $output);
            }

            if ($this->projectionWorkerProcess instanceof Process && !$this->projectionWorkerProcess->isRunning()) {
                $output->writeln(sprintf(
                    '<fg=%s>projection worker stopped, restarting...</>',
                    OutputColorEnum::YELLOW->value,
                ));
                $this->projectionWorkerProcess = $this->startProjectionWorkerProcess($env, $output);
            }

            if ($this->schedulerProcess instanceof Process && !$this->schedulerProcess->isRunning()) {
                $output->writeln(sprintf(
                    '<fg=%s>scheduler stopped, restarting...</>',
                    OutputColorEnum::YELLOW->value,
                ));
                $this->schedulerProcess = $this->startSchedulerProcess($env, $output);
            }

            $this->heartbeat->touch();

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

    /**
     * @param array<string, string>|null $env
     */
    private function startProjectionWorkerProcess(?array $env, FlowSymfonyStyle $flowSymfonyStyle): Process
    {
        $flowcrafterScript = dirname(__DIR__, 3) . '/bin/flowcrafter.php';

        $process = new Process(
            [PHP_BINARY, $flowcrafterScript, 'projection:worker'],
            null,
            $env,
        );
        $process->setTimeout(null);
        $process->start(function (string $type, string $data) use ($flowSymfonyStyle): void {
            $flowSymfonyStyle->write($data);
        });

        return $process;
    }

    /**
     * @param array<string, string>|null $env
     */
    private function startSchedulerProcess(?array $env, FlowSymfonyStyle $flowSymfonyStyle): Process
    {
        $flowcrafterScript = dirname(__DIR__, 3) . '/bin/flowcrafter.php';

        $process = new Process(
            [PHP_BINARY, $flowcrafterScript, 'scheduler'],
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

        if ($this->projectionWorkerProcess?->isRunning()) {
            $this->projectionWorkerProcess->stop();
        }

        if ($this->schedulerProcess?->isRunning()) {
            $this->schedulerProcess->stop();
        }

        if ($this->serverProcess?->isRunning()) {
            $this->serverProcess->stop();
        }

        $this->heartbeat?->cleanup();
    }
}
