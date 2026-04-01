<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Config;

use RuntimeException;
use Wundii\Flowcrafter\Interface\StorageConfigInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;

final class FlowcrafterConfig extends FlowcrafterConfigParameter
{
    public function __construct()
    {
        $this->setParameter(OptionEnum::STORAGE_CONFIG, null);
        $this->setParameter(OptionEnum::SERVER_HOST, null);
        $this->setParameter(OptionEnum::SERVER_PORT, null);
        $this->setParameter(OptionEnum::SERVER_WORKERS, null);
        $this->setParameter(OptionEnum::SERVER_HTTPS, false);
        $this->setParameter(OptionEnum::SERVER_SECRET, null);
        $this->setParameter(OptionEnum::SERVER_DESCRIPTION, null);
        $this->setParameter(OptionEnum::SERVER_STORAGE, getcwd() . '/data/flowcrafter.sqlite');
        $this->setParameter(OptionEnum::DEPENDENCIES_INJECTION, []);
    }

    public function setStorageConfig(StorageConfigInterface $storageConfig): void
    {
        $this->setParameter(OptionEnum::STORAGE_CONFIG, $storageConfig);
    }

    public function setServerHost(?string $serverHost = null): void
    {
        $this->setParameter(OptionEnum::SERVER_HOST, $serverHost);
    }

    public function getServerHost(): ?string
    {
        return $this->getNullOrString(OptionEnum::SERVER_HOST);
    }

    public function setServerPort(?int $serverPort = null): void
    {
        $this->setParameter(OptionEnum::SERVER_PORT, $serverPort);
    }

    public function getServerPort(): ?int
    {
        return $this->getNullOrInt(OptionEnum::SERVER_PORT);
    }

    public function setServerWorkers(?int $serverWorkers = null): void
    {
        $this->setParameter(OptionEnum::SERVER_WORKERS, $serverWorkers);
    }

    public function getServerWorkers(): ?int
    {
        return $this->getNullOrInt(OptionEnum::SERVER_WORKERS);
    }

    public function setServerHttps(bool $serverHttps = true): void
    {
        $this->setParameter(OptionEnum::SERVER_HTTPS, $serverHttps);
    }

    public function getServerHttps(): bool
    {
        return $this->getBoolean(OptionEnum::SERVER_HTTPS);
    }

    public function setServerSecret(?string $serverSecret = null): void
    {
        $this->setParameter(OptionEnum::SERVER_SECRET, $serverSecret);
    }

    public function getServerSecret(): ?string
    {
        return $this->getNullOrString(OptionEnum::SERVER_SECRET);
    }

    public function setServerDescription(?string $serverDescription = null): void
    {
        $this->setParameter(OptionEnum::SERVER_DESCRIPTION, $serverDescription);
    }

    public function getServerDescription(): ?string
    {
        return $this->getNullOrString(OptionEnum::SERVER_DESCRIPTION);
    }

    public function setServerStorage(?string $file): void
    {
        $this->setParameter(OptionEnum::SERVER_STORAGE, $file);
    }

    public function getServerStorage(): ?string
    {
        return $this->getNullOrString(OptionEnum::SERVER_STORAGE);
    }

    /**
     * @param array<class-string|object> $dependenciesInjection
     */
    public function setDependenciesInjection(array $dependenciesInjection = []): void
    {
        $this->setParameter(OptionEnum::DEPENDENCIES_INJECTION, $dependenciesInjection);
    }

    /**
     * @return array<class-string|object>
     */
    public function getDependencyInjections(): array
    {
        /** @var class-string[] $dependencies */
        $dependencies = $this->getArrayWithStrings(OptionEnum::DEPENDENCIES_INJECTION);

        return $dependencies;
    }

    public function getStorage(): StorageInterface
    {
        $storageConfig = $this->getParameter(OptionEnum::STORAGE_CONFIG);
        if (!$storageConfig instanceof StorageConfigInterface) {
            throw new RuntimeException('Storage config is not set. Call setStorageConfig() first.');
        }

        $storageClass = $storageConfig->getStorageClass();

        if (!class_exists($storageClass)) {
            throw new RuntimeException('The storage class ' . $storageClass . ' does not exist.');
        }

        $storage = new $storageClass($storageConfig, $this->getServerStorage());
        if (!$storage instanceof StorageInterface) {
            throw new RuntimeException('The storage class ' . $storageClass . ' does not implement StorageInterface.');
        }

        return $storage;
    }
}
