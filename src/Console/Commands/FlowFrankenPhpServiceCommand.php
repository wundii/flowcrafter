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
use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;

final class FlowFrankenPhpServiceCommand extends Command
{
    private ?Process $serverProcess = null;

    public function __construct(
        private BootstrapConfig $bootstrapConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('frankenphp:service');
        $this->setDescription('Start the API server (FrankenPHP) standalone');
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Server host', '0.0.0.0');
        $this->addOption('port', null, InputOption::VALUE_REQUIRED, 'Server port', '8000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $frankenPhpBinary = (new ExecutableFinder())->find('frankenphp');
        if ($frankenPhpBinary === null) {
            throw new RuntimeException('FrankenPHP binary not found — install FrankenPHP to use this command');
        }

        /** @var string $host */
        $host = $input->getOption('host');
        /** @var string $port */
        $port = $input->getOption('port');
        $serviceDir = dirname(__DIR__, 3) . '/service';

        $env = [];
        $configFile = $this->bootstrapConfig->getBootstrapConfigFile();
        if ($configFile !== null) {
            $env['FLOWCRAFTER_CONFIG'] = $configFile;
        }

        $output->writeln(sprintf(
            '<fg=%s>starting API server (FrankenPHP) on %s:%s</>',
            OutputColorEnum::BLUE->value,
            $host,
            $port,
        ));

        $serverProcess = new Process(
            [$frankenPhpBinary, 'php-server', '--listen', sprintf('tcp4/%s:%s', $host, $port), '--root', $serviceDir],
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

        $output->writeln('');
        $serverProcess->wait(function (string $type, string $data) use ($output): void {
            $output->write($data);
        });

        $output->writeln('<fg=red>API server stopped</>');

        return Command::FAILURE;
    }

    private function cleanup(): void
    {
        if ($this->serverProcess?->isRunning()) {
            $this->serverProcess->stop();
        }
    }
}
