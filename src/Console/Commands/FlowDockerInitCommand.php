<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Wundii\Flowcrafter\Console\ComposerExtensionResolver;
use Wundii\Flowcrafter\Console\FlowConsole;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;

final class FlowDockerInitCommand extends Command
{
    /**
     * @var array<string, string|null>
     */
    public const PDO_DRIVER_CHOICES = [
        'MySQL / MariaDB' => 'pdo_mysql',
        'PostgreSQL' => 'pdo_pgsql',
        'None (Redis / EventSourcingDB)' => null,
    ];

    public function __construct(
        private readonly ComposerExtensionResolver $composerExtensionResolver,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('docker:init');
        $this->setDescription('Generate Dockerfile and docker-compose.yml for service, observer, and scheduler');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new FlowSymfonyStyle($input, $output);
        $output->startApplication(FlowConsole::vendorVersion());

        $templatesDir = dirname(__DIR__, 3) . '/templates';
        $projectDir = (string) getcwd();

        $exitCode = Command::SUCCESS;

        $exitCode = $this->generateDockerfile($output, $templatesDir, $projectDir, $exitCode);
        $exitCode = $this->generateFile($output, $templatesDir . '/docker-compose.yml.dist', $projectDir . '/docker-compose.yml', 'docker-compose.yml', $exitCode);

        $output->writeln('');

        return $exitCode;
    }

    private function generateDockerfile(FlowSymfonyStyle $flowSymfonyStyle, string $templatesDir, string $projectDir, int $exitCode): int
    {
        $targetPath = $projectDir . '/Dockerfile';

        if ($this->filesystem->exists($targetPath) && !$flowSymfonyStyle->confirm('Dockerfile already exists. Overwrite?', false)) {
            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>Dockerfile skipped</>',
                OutputColorEnum::YELLOW->value,
            ));
            return $exitCode;
        }

        $extensions = $this->composerExtensionResolver->resolve($projectDir);
        $extensions = $this->resolvePdoDriver($flowSymfonyStyle, $extensions);
        if (!in_array('pdo_sqlite', $extensions, true)) {
            array_unshift($extensions, 'pdo_sqlite');
        }

        try {
            $template = file_get_contents($templatesDir . '/Dockerfile.dist');
            if ($template === false) {
                throw new Exception('Failed to read Dockerfile template');
            }

            $content = str_replace('{{EXTENSIONS}}', implode(' ', $extensions), $template);
            $this->filesystem->dumpFile($targetPath, $content);

            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>generated Dockerfile with extensions: %s</>',
                OutputColorEnum::GREEN->value,
                implode(', ', $extensions),
            ));
        } catch (Exception $exception) {
            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>failed to generate Dockerfile: %s</>',
                OutputColorEnum::RED->value,
                $exception->getMessage(),
            ));
            return Command::FAILURE;
        }

        return $exitCode;
    }

    /**
     * @param string[] $extensions
     * @return string[]
     */
    private function resolvePdoDriver(FlowSymfonyStyle $flowSymfonyStyle, array $extensions): array
    {
        $hasPdoBase = in_array('pdo', $extensions, true);
        $hasSpecificDriver = array_filter($extensions, static fn (string $ext): bool => str_starts_with($ext, 'pdo_'));

        $extensions = array_values(array_filter($extensions, static fn (string $ext): bool => $ext !== 'pdo'));

        if (!$hasPdoBase || $hasSpecificDriver !== []) {
            return $extensions;
        }

        $choiceKeys = array_keys(self::PDO_DRIVER_CHOICES);
        $choice = $flowSymfonyStyle->choice(
            'ext-pdo detected but no specific driver found. Which primary database do you use?',
            $choiceKeys,
        );
        $choice = is_string($choice) ? $choice : $choiceKeys[0];

        $driver = self::PDO_DRIVER_CHOICES[$choice] ?? null;
        if ($driver !== null && !in_array($driver, $extensions, true)) {
            $extensions[] = $driver;
            sort($extensions);
        }

        return $extensions;
    }

    private function generateFile(FlowSymfonyStyle $flowSymfonyStyle, string $templatePath, string $targetPath, string $name, int $exitCode): int
    {
        if ($this->filesystem->exists($targetPath) && !$flowSymfonyStyle->confirm(sprintf('%s already exists. Overwrite?', $name), false)) {
            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>%s skipped</>',
                OutputColorEnum::YELLOW->value,
                $name,
            ));
            return $exitCode;
        }

        try {
            $this->filesystem->copy($templatePath, $targetPath);
            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>generated %s</>',
                OutputColorEnum::GREEN->value,
                $name,
            ));
        } catch (Exception $exception) {
            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>failed to generate %s: %s</>',
                OutputColorEnum::RED->value,
                $name,
                $exception->getMessage(),
            ));
            return Command::FAILURE;
        }

        return $exitCode;
    }
}
