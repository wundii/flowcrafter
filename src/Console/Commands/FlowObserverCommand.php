<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Throwable;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Heartbeat;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\FlowObserver;

final class FlowObserverCommand extends Command
{
    /**
     * @var Process[]
     */
    private array $workerProcesses = [];

    public function __construct(
        private FlowcrafterConfig $flowcrafterConfig,
        private BootstrapConfig $bootstrapConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('observer');
        $this->setDescription('Start observer worker(s) for queue processing');
        $this->addOption('workers', null, InputOption::VALUE_REQUIRED, 'Number of observer workers', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        /** @var string $workersOption */
        $workersOption = $input->getOption('workers');
        $workers = max(1, (int) $workersOption);

        $this->flowcrafterConfig->initializeStorage($output);

        $output->writeln(sprintf(
            '<fg=%s>starting %d observer worker(s)</>',
            OutputColorEnum::BLUE->value,
            $workers,
        ));
        $output->writeln('');

        if ($workers === 1) {
            return $this->runObserver($output);
        }

        return $this->runWorkers($workers, $output);
    }

    private function runObserver(FlowSymfonyStyle $flowSymfonyStyle): int
    {
        $heartbeat = new Heartbeat();

        register_shutdown_function(static function () use ($heartbeat): void {
            $heartbeat->cleanup();
        });

        $storage = $this->flowcrafterConfig->getStorage();
        $dependencyInjections = $this->flowcrafterConfig->getDependencyInjections();
        $flowObserver = new FlowObserver($storage, $dependencyInjections);

        $logger = static function (string $message) use ($flowSymfonyStyle): void {
            $flowSymfonyStyle->writeln($message);
        };

        $heartbeat->touch();

        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            try {
                $flowObserver->run(maxExecutionTimeInSeconds: 10.0, logger: $logger, heartbeat: $heartbeat);
            } catch (Throwable $e) {
                $flowSymfonyStyle->writeln('[Observer] error: ' . $e->getMessage());
                sleep(2);
            }

            $heartbeat->touch();
        }
    }

    private function runWorkers(int $workers, FlowSymfonyStyle $flowSymfonyStyle): int
    {
        $flowcrafterScript = dirname(__DIR__, 3) . '/bin/flowcrafter.php';

        $env = $this->bootstrapConfig->getProcessEnv();

        register_shutdown_function(function (): void {
            $this->cleanup();
        });

        for ($i = 0; $i < $workers; ++$i) {
            $this->workerProcesses[$i] = $this->startWorker($flowcrafterScript, $env, $i, $flowSymfonyStyle);
        }

        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            foreach ($this->workerProcesses as $i => $process) {
                if (!$process->isRunning()) {
                    $flowSymfonyStyle->writeln(sprintf(
                        '<fg=%s>worker %d stopped, restarting...</>',
                        OutputColorEnum::YELLOW->value,
                        $i,
                    ));
                    $this->workerProcesses[$i] = $this->startWorker($flowcrafterScript, $env, $i, $flowSymfonyStyle);
                }
            }

            sleep(1);
        }
    }

    /**
     * @param array<string, string>|null $env
     */
    private function startWorker(string $flowcrafterScript, ?array $env, int $index, FlowSymfonyStyle $flowSymfonyStyle): Process
    {
        $process = new Process(
            [PHP_BINARY, $flowcrafterScript, 'observer', '--workers', '1'],
            null,
            $env,
        );
        $process->setTimeout(null);
        $process->start(function (string $type, string $data) use ($flowSymfonyStyle, $index): void {
            $prefix = sprintf('[worker %d] ', $index);
            $lines = explode("\n", $data);
            foreach ($lines as $i => $line) {
                if ($i === count($lines) - 1 && $line === '') {
                    $flowSymfonyStyle->write("\n");
                    continue;
                }

                $flowSymfonyStyle->write($prefix . $line . ($i < count($lines) - 1 ? "\n" : ''));
            }
        });

        return $process;
    }

    private function cleanup(): void
    {
        foreach ($this->workerProcesses as $workerProcess) {
            if ($workerProcess->isRunning()) {
                $workerProcess->stop();
            }
        }
    }
}
