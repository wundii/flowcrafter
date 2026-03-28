<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\FlowObserver;

final class FlowFrankenPhpObserverCommand extends Command
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
        $this->setName('frankenphp:observer');
        $this->setDescription('Start observer worker(s) via FrankenPHP');
        $this->addOption('workers', null, InputOption::VALUE_REQUIRED, 'Number of observer workers', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $frankenPhpBinary = (new ExecutableFinder())->find('frankenphp');
        if ($frankenPhpBinary === null) {
            throw new RuntimeException('FrankenPHP binary not found — install FrankenPHP to use this command');
        }

        /** @var string $workersOption */
        $workersOption = $input->getOption('workers');
        $workers = max(1, (int) $workersOption);

        $output->writeln(sprintf(
            '<fg=%s>starting %d observer worker(s)</>',
            OutputColorEnum::BLUE->value,
            $workers,
        ));
        $output->writeln('');

        if ($workers === 1) {
            return $this->runObserver($output);
        }

        return $this->runWorkers($frankenPhpBinary, $workers, $output);
    }

    private function runObserver(FlowSymfonyStyle $flowSymfonyStyle): int
    {
        $pidFile = sys_get_temp_dir() . '/flowcrafter-observer.pid';
        file_put_contents($pidFile, (string) getmypid());

        register_shutdown_function(static function () use ($pidFile): void {
            @unlink($pidFile);
        });

        $storage = $this->flowcrafterConfig->getStorage();
        $dependencyInjections = $this->flowcrafterConfig->getDependencyInjections();
        $flowObserver = new FlowObserver($storage, $dependencyInjections);

        $logger = static function (string $message) use ($flowSymfonyStyle): void {
            $flowSymfonyStyle->writeln($message);
        };

        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            try {
                $flowObserver->run(logger: $logger);
            } catch (Throwable $e) {
                $flowSymfonyStyle->writeln('[Observer] error: ' . $e->getMessage());
                sleep(2);
            }
        }
    }

    private function runWorkers(string $frankenPhpBinary, int $workers, FlowSymfonyStyle $flowSymfonyStyle): int
    {
        $flowcrafterScript = dirname(__DIR__, 3) . '/bin/flowcrafter.php';

        $env = [];
        $configFile = $this->bootstrapConfig->getBootstrapConfigFile();
        if ($configFile !== null) {
            $env['FLOWCRAFTER_CONFIG'] = $configFile;
        }

        register_shutdown_function(function (): void {
            $this->cleanup();
        });

        for ($i = 0; $i < $workers; ++$i) {
            $this->workerProcesses[$i] = $this->startWorker($frankenPhpBinary, $flowcrafterScript, $env, $i, $flowSymfonyStyle);
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
                    $this->workerProcesses[$i] = $this->startWorker($frankenPhpBinary, $flowcrafterScript, $env, $i, $flowSymfonyStyle);
                }
            }

            sleep(1);
        }
    }

    /**
     * @param array<string, string> $env
     */
    private function startWorker(string $frankenPhpBinary, string $flowcrafterScript, array $env, int $index, FlowSymfonyStyle $flowSymfonyStyle): Process
    {
        $process = new Process(
            [$frankenPhpBinary, $flowcrafterScript, 'frankenphp:observer', '--workers', '1'],
            null,
            $env,
        );
        $process->setTimeout(null);
        $process->start(function (string $type, string $data) use ($flowSymfonyStyle, $index): void {
            $flowSymfonyStyle->write(sprintf('[worker %d] %s', $index, $data));
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
