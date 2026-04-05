<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Console\Preflight;

use ReflectionClass;
use Throwable;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfig;
use Wundii\Flowcrafter\Bootstrap\BootstrapConfigInitializer;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\Interface\StorageInterface;

final readonly class StoragePreflight
{
    public function __construct(
        private BootstrapConfig $bootstrapConfig,
        private FlowcrafterConfig $flowcrafterConfig,
        private BootstrapConfigInitializer $bootstrapConfigInitializer,
    ) {
    }

    public function ensureReady(FlowSymfonyStyle $flowSymfonyStyle): bool
    {
        $flowSymfonyStyle->writeln('<fg=blue;options=bold>Pre-flight check:</>');

        $configFile = $this->bootstrapConfig->getBootstrapConfigFile();
        $configOk = $configFile !== null;
        $this->renderRow(
            $flowSymfonyStyle,
            'Config file',
            $configOk,
            $configOk ? (string) $configFile : BootstrapConfig::DEFAULT_CONFIG_FILE . ' — missing',
        );

        if (!$configOk) {
            $flowSymfonyStyle->writeln('');
            $created = $this->bootstrapConfigInitializer->createConfig((string) getcwd());
            if ($created === null) {
                $flowSymfonyStyle->writeln(sprintf(
                    '<fg=%s>Aborted. Run `bin/flowcrafter config:create` and adjust %s, then retry.</>',
                    OutputColorEnum::RED->value,
                    BootstrapConfig::DEFAULT_CONFIG_FILE,
                ));
                return false;
            }

            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>Please review %s and run `bin/flowcrafter dev` again.</>',
                OutputColorEnum::YELLOW->value,
                $created,
            ));
            return false;
        }

        try {
            $storage = $this->flowcrafterConfig->getStorage();
        } catch (Throwable $throwable) {
            $flowSymfonyStyle->writeln('');
            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>Storage not configured: %s</>',
                OutputColorEnum::RED->value,
                $throwable->getMessage(),
            ));
            return false;
        }

        $backendName = (new ReflectionClass($storage))->getShortName();
        $serviceStorageFile = $this->flowcrafterConfig->getServerStorage() ?? ':memory:';

        $serviceOk = $storage->isServiceStorageInitialized();
        $primaryOk = $storage->isPrimaryStorageInitialized();

        $this->renderRow($flowSymfonyStyle, 'Service DB', $serviceOk, sprintf('SQLite (%s)', $serviceStorageFile));
        $this->renderRow($flowSymfonyStyle, 'Primary DB', $primaryOk, $backendName);

        $drift = ($serviceOk && $primaryOk) ? $this->detectDrift($storage) : null;
        if ($drift !== null) {
            $this->renderWarnRow($flowSymfonyStyle, 'Sync drift', sprintf(
                'service: %d / primary: %d flows',
                $drift['service'],
                $drift['primary'],
            ));
        }

        $flowSymfonyStyle->writeln('');

        if ($serviceOk && $primaryOk) {
            if ($drift !== null) {
                $this->offerRebuild($flowSymfonyStyle, $storage);
            }

            return true;
        }

        if (!$flowSymfonyStyle->confirm('Initialize missing storage now?', true)) {
            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>Aborted. Run `bin/flowcrafter storage:init` to initialize storage.</>',
                OutputColorEnum::RED->value,
            ));
            return false;
        }

        try {
            $this->flowcrafterConfig->initializeStorage($flowSymfonyStyle);
        } catch (Throwable $throwable) {
            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>Initialization failed: %s</>',
                OutputColorEnum::RED->value,
                $throwable->getMessage(),
            ));
            return false;
        }

        $drift = $this->detectDrift($storage);
        if ($drift !== null) {
            $this->renderWarnRow($flowSymfonyStyle, 'Sync drift', sprintf(
                'service: %d / primary: %d flows',
                $drift['service'],
                $drift['primary'],
            ));
            $flowSymfonyStyle->writeln('');
            $this->offerRebuild($flowSymfonyStyle, $storage);
        }

        $flowSymfonyStyle->writeln('');
        return true;
    }

    private function offerRebuild(FlowSymfonyStyle $flowSymfonyStyle, StorageInterface $storage): void
    {
        if (!$flowSymfonyStyle->confirm('Run `storage:rebuild --clear` now to re-sync the service index?', true)) {
            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>Skipped. Run `bin/flowcrafter storage:rebuild --clear` manually when ready.</>',
                OutputColorEnum::YELLOW->value,
            ));
            $flowSymfonyStyle->writeln('');
            return;
        }

        try {
            $storage->truncateFlowList();

            $flowHashes = iterator_to_array($storage->findAllFlowHashes());
            $count = 0;
            foreach ($flowHashes as $flowHash) {
                $flow = $storage->findFlowByHash($flowHash);
                if ($flow instanceof Flow) {
                    $storage->saveFlow($flow);
                    ++$count;
                }
            }

            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>Rebuilt %d of %d flow(s).</>',
                OutputColorEnum::GREEN->value,
                $count,
                count($flowHashes),
            ));
        } catch (Throwable $throwable) {
            $flowSymfonyStyle->writeln(sprintf(
                '<fg=%s>Rebuild failed: %s</>',
                OutputColorEnum::RED->value,
                $throwable->getMessage(),
            ));
        }

        $flowSymfonyStyle->writeln('');
    }

    private function renderRow(FlowSymfonyStyle $flowSymfonyStyle, string $label, bool $ok, string $detail): void
    {
        $icon = $ok
            ? sprintf('<fg=%s>[ OK ]</>', OutputColorEnum::GREEN->value)
            : sprintf('<fg=%s>[MISS]</>', OutputColorEnum::RED->value);

        $flowSymfonyStyle->writeln(sprintf('  %s  %-12s %s', $icon, $label, $detail));
    }

    private function renderWarnRow(FlowSymfonyStyle $flowSymfonyStyle, string $label, string $detail): void
    {
        $icon = sprintf('<fg=%s>[WARN]</>', OutputColorEnum::YELLOW->value);

        $flowSymfonyStyle->writeln(sprintf('  %s  %-12s %s', $icon, $label, $detail));
    }

    /**
     * @return array{service: int, primary: int}|null
     */
    private function detectDrift(StorageInterface $storage): ?array
    {
        $serviceCount = $storage->countFlows();
        $primaryCount = iterator_count($storage->findAllFlowHashes());

        if ($serviceCount === $primaryCount) {
            return null;
        }

        return [
            'service' => $serviceCount,
            'primary' => $primaryCount,
        ];
    }
}
