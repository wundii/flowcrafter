<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Config;

use ReflectionClass;
use RuntimeException;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Console\Output\FlowSymfonyStyle;
use Wundii\Flowcrafter\Console\OutputColorEnum;
use Wundii\Flowcrafter\Interface\QueueConfigInterface;
use Wundii\Flowcrafter\Interface\QueueInterface;
use Wundii\Flowcrafter\Interface\StorageConfigInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Queue\Config\RedisQueueConfig;

final class FlowcrafterConfig extends FlowcrafterConfigParameter
{
    public function __construct()
    {
        $this->setParameter(OptionEnum::STORAGE_CONFIG, null);
        $this->setParameter(OptionEnum::QUEUE_CONFIG, null);
        $this->setParameter(OptionEnum::SERVER_HOST, null);
        $this->setParameter(OptionEnum::SERVER_PORT, null);
        $this->setParameter(OptionEnum::SERVER_WORKERS, null);
        $this->setParameter(OptionEnum::SERVER_NUM_THREADS, null);
        $this->setParameter(OptionEnum::SERVER_HTTPS, false);
        $this->setParameter(OptionEnum::SERVER_SECRET, null);
        $this->setParameter(OptionEnum::SERVER_DESCRIPTION, null);
        $this->setParameter(OptionEnum::SERVER_STORAGE, null);
        $this->setParameter(OptionEnum::DEPENDENCIES_INJECTION, []);
    }

    public function getServerDev(): bool
    {
        return getenv('FLOWCRAFTER_DEV') === '1';
    }

    public function setStorageConfig(StorageConfigInterface $storageConfig): void
    {
        $this->setParameter(OptionEnum::STORAGE_CONFIG, $storageConfig);
    }

    public function setQueueConfig(QueueConfigInterface $queueConfig): void
    {
        $this->setParameter(OptionEnum::QUEUE_CONFIG, $queueConfig);
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

    public function setServerNumThreads(?int $serverNumThreads = null): void
    {
        $this->setParameter(OptionEnum::SERVER_NUM_THREADS, $serverNumThreads);
    }

    public function getServerNumThreads(): ?int
    {
        return $this->getNullOrInt(OptionEnum::SERVER_NUM_THREADS);
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
     * @param array<int|class-string, class-string|object> $dependenciesInjection
     *        Numeric key: class-string (autowire) or object (synthetic)
     *        String key:  interface class-string => object (interface binding)
     */
    public function setDependenciesInjection(array $dependenciesInjection = []): void
    {
        $this->setParameter(OptionEnum::DEPENDENCIES_INJECTION, $dependenciesInjection);
    }

    /**
     * @return array<int|class-string, class-string|object>
     */
    public function getDependencyInjections(): array
    {
        /** @var array<int|class-string, class-string|object> $dependencies */
        $dependencies = Assert::array($this->getParameter(OptionEnum::DEPENDENCIES_INJECTION, []));

        return $dependencies;
    }

    public function initializeStorage(FlowSymfonyStyle $flowSymfonyStyle): StorageInterface
    {
        $storage = $this->getStorage();
        $storageClass = (new ReflectionClass($storage))->getShortName();
        $storage->initializeDatabase();
        $flowSymfonyStyle->writeln(sprintf(
            '<fg=%s>%s database initialized</>',
            OutputColorEnum::DEFAULT->value,
            $storageClass,
        ));

        return $storage;
    }

    public function getStorage(): StorageInterface
    {
        static $cachedStorage = null;
        if ($cachedStorage instanceof StorageInterface) {
            return $cachedStorage;
        }

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

        $cachedStorage = $storage;

        return $storage;
    }

    public function initializeQueue(FlowSymfonyStyle $flowSymfonyStyle): QueueInterface
    {
        $queue = $this->getQueue();
        $queueClass = (new ReflectionClass($queue))->getShortName();
        $queue->initializeQueue();
        $flowSymfonyStyle->writeln(sprintf(
            '<fg=%s>%s queue initialized</>',
            OutputColorEnum::DEFAULT->value,
            $queueClass,
        ));

        return $queue;
    }

    public function getQueue(): QueueInterface
    {
        static $cachedQueue = null;
        if ($cachedQueue instanceof QueueInterface) {
            return $cachedQueue;
        }

        $queueConfig = $this->getParameter(OptionEnum::QUEUE_CONFIG);
        if (!$queueConfig instanceof QueueConfigInterface) {
            // Redis is the default queue backend.
            $queueConfig = new RedisQueueConfig('127.0.0.1', 6379);
        }

        $queueClass = $queueConfig->getQueueClass();

        if (!class_exists($queueClass)) {
            throw new RuntimeException('The queue class ' . $queueClass . ' does not exist.');
        }

        $queue = new $queueClass($queueConfig);
        if (!$queue instanceof QueueInterface) {
            throw new RuntimeException('The queue class ' . $queueClass . ' does not implement QueueInterface.');
        }

        $cachedQueue = $queue;

        return $queue;
    }
}
