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
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\FlowObserver;

final class FlowServeCommand extends Command
{
    private ?Process $serverProcess = null;

    private string $pidFile;

    public function __construct(
        private FlowcrafterConfig $flowcrafterConfig
    ) {
        $this->pidFile = sys_get_temp_dir() . '/flowcrafter-observer.pid';
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('serve');
        $this->setDescription('Start the API server and observer together');
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Server host', '0.0.0.0');
        $this->addOption('port', null, InputOption::VALUE_REQUIRED, 'Server port', '8000');
        $this->addOption('no-observer', null, InputOption::VALUE_NONE, 'Start only the API server without the observer');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        /** @var string $host */
        $host = $input->getOption('host');
        /** @var string $port */
        $port = $input->getOption('port');
        $noObserver = (bool) $input->getOption('no-observer');

        $serviceIndex = dirname(__DIR__, 3) . '/service/index.php';

        $output->writeln(sprintf(
            '<fg=%s>starting API server on %s:%s</>',
            OutputColorEnum::BLUE->value,
            $host,
            $port,
        ));

        $serverProcess = new Process(
            [PHP_BINARY, '-S', sprintf('%s:%s', $host, $port), $serviceIndex],
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

        if ($noObserver) {
            $output->writeln(sprintf(
                '<fg=%s>observer disabled — running API server only</>',
                OutputColorEnum::YELLOW->value,
            ));
            $output->writeln('');

            $serverProcess->wait();

            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<fg=%s>starting observer</>',
            OutputColorEnum::BLUE->value,
        ));
        $output->writeln('');

        file_put_contents($this->pidFile, (string) getmypid());

        $storage = $this->flowcrafterConfig->getStorage();
        $flowObserver = new FlowObserver($storage);

        $logger = static function (string $message) use ($output): void {
            $output->writeln($message);
        };

        /** @phpstan-ignore while.alwaysTrue */
        while ($serverProcess->isRunning()) {
            try {
                $flowObserver->run(logger: $logger);
            } catch (Throwable $e) {
                $output->writeln('[Observer] error: ' . $e->getMessage());
                sleep(2);
            }
        }

        /** @phpstan-ignore deadCode.unreachable */
        $output->writeln('<fg=red>API server stopped unexpectedly</>');

        return Command::FAILURE;
    }

    private function cleanup(): void
    {
        if ($this->serverProcess?->isRunning()) {
            $this->serverProcess->stop();
        }

        @unlink($this->pidFile);
    }
}
