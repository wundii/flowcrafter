<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use ReflectionClass;
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

final class FlowFrankenPhpServiceCommand extends Command
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
        $this->setName('frankenphp:service');
        $this->setDescription('Start the API server (FrankenPHP worker mode) standalone');
        $this->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Server host');
        $this->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Server port');
        $this->addOption('workers', null, InputOption::VALUE_OPTIONAL, 'Number of PHP workers');
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
        $https = $this->flowcrafterConfig->getServerHttps();
        $serviceDir = dirname(__DIR__, 3) . '/service';

        $caddyfile = $this->buildCaddyfile($host, $port, $serviceDir, $workers, $https);

        $env = [];
        $configFile = $this->bootstrapConfig->getBootstrapConfigFile();
        if ($configFile !== null) {
            $env['FLOWCRAFTER_CONFIG'] = $configFile;
        }

        $storage = $this->flowcrafterConfig->getStorage();
        $storageClass = (new ReflectionClass($storage))->getShortName();
        $storage->initializeDatabase();

        $output->writeln(sprintf(
            '<fg=%s>%s database initialized</>',
            OutputColorEnum::DEFAULT->value,
            $storageClass,
        ));

        $output->writeln(sprintf(
            '<fg=%s>starting API server (FrankenPHP worker mode) on %s:%s with %s worker(s)</>',
            OutputColorEnum::BLUE->value,
            $host,
            $port,
            $workers,
        ));
        $output->writeln(sprintf(
            '<fg=%s>with Caddyfile: %s</>',
            OutputColorEnum::DEFAULT->value,
            $caddyfile,
        ));

        $serverProcess = new Process(
            [$frankenPhpBinary, 'run', '--config', $caddyfile, '--adapter', 'caddyfile'],
            null,
            $env !== [] ? $env : null,
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

    private function buildCaddyfile(string $host, string $port, string $serviceDir, string $workers, bool $https): string
    {
        $autoHttps = $https ? '' : "\n\tauto_https off";
        $scheme = $https ? 'https' : 'http';

        $content = <<<CADDYFILE
            {
            	frankenphp
            	order php_server before file_server{$autoHttps}
            }

            {$scheme}://:{$port} {
            	bind {$host}
            	root * {$serviceDir}

            	php_server {
            		worker {$serviceDir}/worker.php {$workers}
            	}
            }
            CADDYFILE;

        $tempFile = tempnam(sys_get_temp_dir(), 'flowcrafter_caddyfile_');
        if ($tempFile === false) {
            throw new RuntimeException('Failed to create temporary Caddyfile');
        }

        file_put_contents($tempFile, $content);
        $this->tempCaddyfile = $tempFile;

        return $tempFile;
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
