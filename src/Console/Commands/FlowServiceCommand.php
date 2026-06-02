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
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\Service\CaddyfileBuilder;

final class FlowServiceCommand extends Command
{
    private const DEFAULT_HOST = '0.0.0.0';

    private const DEFAULT_PORT = 8000;

    private const DEFAULT_WORKERS = 4;

    private ?Process $serverProcess = null;

    private ?string $tempCaddyfile = null;

    public function __construct(
        private BootstrapConfig $bootstrapConfig,
        private FlowcrafterConfig $flowcrafterConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('service');
        $this->setDescription('Start the API server (FrankenPHP worker mode) standalone');
        $this->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Server host');
        $this->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Server port');
        $this->addOption('workers', null, InputOption::VALUE_OPTIONAL, 'Number of PHP workers');
        $this->addOption('num-threads', null, InputOption::VALUE_OPTIONAL, 'Total PHP thread pool size (defaults to workers x 2)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $frankenPhpBinary = (new ExecutableFinder())->find('frankenphp');
        if ($frankenPhpBinary === null) {
            throw new RuntimeException('FrankenPHP binary not found — install FrankenPHP to use this command');
        }

        $host = $this->resolveOption($input, 'host', $this->flowcrafterConfig->getServerHost(), self::DEFAULT_HOST);
        $port = $this->resolveOption($input, 'port', (string) ($this->flowcrafterConfig->getServerPort() ?? ''), (string) self::DEFAULT_PORT);
        $workers = $this->resolveOption($input, 'workers', (string) ($this->flowcrafterConfig->getServerWorkers() ?? ''), (string) self::DEFAULT_WORKERS);
        $numThreads = $this->resolveNumThreads($input);
        $https = $this->flowcrafterConfig->getServerHttps();
        $serviceDir = dirname(__DIR__, 3) . '/service';

        $caddyfile = $this->writeCaddyfile(new CaddyfileBuilder(
            host: $host,
            port: (int) $port,
            workers: (int) $workers,
            numThreads: $numThreads,
            https: $https,
            serviceDir: $serviceDir,
        ));

        $env = $this->bootstrapConfig->getProcessEnv();

        $this->flowcrafterConfig->initializeStorage($output);
        $this->flowcrafterConfig->initializeQueue($output);

        $output->writeln(sprintf(
            '<fg=%s>starting API server (FrankenPHP worker mode) on %s:%s with %s worker(s)</>',
            OutputColorEnum::BLUE->value,
            $host,
            $port,
            $workers,
        ));
        $output->writeln(sprintf(
            '<fg=%s>Caddyfile: %s</>',
            OutputColorEnum::DEFAULT->value,
            $caddyfile,
        ));
        $output->writeln(sprintf(
            '<fg=%s>Service Storage: %s</>',
            OutputColorEnum::DEFAULT->value,
            $this->flowcrafterConfig->getServerStorage() ?? '(disabled)',
        ));

        $serverProcess = new Process(
            [$frankenPhpBinary, 'run', '--config', $caddyfile, '--adapter', 'caddyfile'],
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

    private function writeCaddyfile(CaddyfileBuilder $caddyfileBuilder): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'flowcrafter_caddyfile_');
        if ($tempFile === false) {
            throw new RuntimeException('Failed to create temporary Caddyfile');
        }

        file_put_contents($tempFile, $caddyfileBuilder->build());
        $this->tempCaddyfile = $tempFile;

        return $tempFile;
    }

    private function resolveNumThreads(InputInterface $input): ?int
    {
        /** @var ?string $cliValue */
        $cliValue = $input->getOption('num-threads');
        if ($cliValue !== null && $cliValue !== '') {
            return (int) $cliValue;
        }

        return $this->flowcrafterConfig->getServerNumThreads();
    }

    private function resolveOption(InputInterface $input, string $option, ?string $configValue, string $default): string
    {
        /** @var ?string $cliValue */
        $cliValue = $input->getOption($option);

        if ($cliValue !== null && $cliValue !== '') {
            return $cliValue;
        }

        if ($configValue !== null && $configValue !== '') {
            return $configValue;
        }

        return $default;
    }

    private function cleanup(): void
    {
        if ($this->serverProcess?->isRunning()) {
            $this->serverProcess->stop();
        }

        if ($this->tempCaddyfile !== null && file_exists($this->tempCaddyfile)) {
            unlink($this->tempCaddyfile);
        }
    }
}
