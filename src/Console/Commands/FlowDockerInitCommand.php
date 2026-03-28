<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;

final class FlowDockerInitCommand extends Command
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('docker:init');
        $this->setDescription('Generate Dockerfiles and docker-compose.yml for service and observer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $templatesDir = dirname(__DIR__, 3) . '/templates';
        $projectDir = (string) getcwd();

        $files = [
            'Dockerfile.service' => $templatesDir . '/Dockerfile.service.dist',
            'Dockerfile.observer' => $templatesDir . '/Dockerfile.observer.dist',
            'docker-compose.yml' => $templatesDir . '/docker-compose.yml.dist',
        ];

        $exitCode = Command::SUCCESS;

        foreach ($files as $target => $template) {
            $targetPath = $projectDir . DIRECTORY_SEPARATOR . $target;

            if ($this->filesystem->exists($targetPath)) {
                $output->writeln(sprintf(
                    '<fg=%s>%s already exists, skipping</>',
                    OutputColorEnum::YELLOW->value,
                    $target,
                ));
                continue;
            }

            try {
                $this->filesystem->copy($template, $targetPath);
                $output->writeln(sprintf(
                    '<fg=%s>generated %s</>',
                    OutputColorEnum::GREEN->value,
                    $target,
                ));
            } catch (Exception $exception) {
                $output->writeln(sprintf(
                    '<fg=%s>failed to generate %s: %s</>',
                    OutputColorEnum::RED->value,
                    $target,
                    $exception->getMessage(),
                ));
                $exitCode = Command::FAILURE;
            }
        }

        $output->writeln('');

        return $exitCode;
    }
}
