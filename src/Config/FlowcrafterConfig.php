<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Config;

use RuntimeException;
use Wundii\DataMapper\DataConfig;
use Wundii\DataMapper\DataMapper;
use Wundii\DataMapper\Enum\ApproachEnum;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Interface\StorageInterface;

final class FlowcrafterConfig extends FlowcrafterConfigParameter
{
    public function __construct()
    {
        $this->setParameter(OptionEnum::STORAGE_CLASS, null);
        $this->setParameter(OptionEnum::URL, null);
        $this->setParameter(OptionEnum::APITOKEN, null);
        $this->setParameter(OptionEnum::HOST, null);
        $this->setParameter(OptionEnum::PORT, null);
        $this->setParameter(OptionEnum::USERNAME, null);
        $this->setParameter(OptionEnum::PASSWORD, null);
        $this->setParameter(OptionEnum::DATABASE, null);
    }

    public function setStorageClass(string $storageClass): void
    {
        $this->setParameter(OptionEnum::STORAGE_CLASS, $storageClass);
    }

    public function setUrl(?string $url = null): void
    {
        $this->setParameter(OptionEnum::URL, $url);
    }

    public function setApiToken(?string $apiToken = null): void
    {
        $this->setParameter(OptionEnum::APITOKEN, $apiToken);
    }

    public function setHost(string $host): void
    {
        $this->setParameter(OptionEnum::HOST, $host);
    }

    public function setPort(int $port): void
    {
        $this->setParameter(OptionEnum::PORT, $port);
    }

    public function setUsername(?string $username = null): void
    {
        $this->setParameter(OptionEnum::USERNAME, $username);
    }

    public function setPassword(?string $password = null): void
    {
        $this->setParameter(OptionEnum::PASSWORD, $password);
    }

    public function setDatabase(?string $database = null): void
    {
        $this->setParameter(OptionEnum::DATABASE, $database);
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return [
            'url' => $this->getNullOrString(OptionEnum::URL),
            'apiToken' => $this->getNullOrString(OptionEnum::APITOKEN),
            'host' => $this->getNullOrString(OptionEnum::HOST),
            'port' => $this->getNullOrInt(OptionEnum::PORT),
            'username' => $this->getNullOrString(OptionEnum::USERNAME),
            'password' => $this->getNullOrString(OptionEnum::PASSWORD),
            'database' => $this->getNullOrString(OptionEnum::DATABASE),
        ];
    }

    public function getStorage(): StorageInterface
    {
        $dataConfig = new DataConfig(
            approachEnum: ApproachEnum::CONSTRUCTOR,
        );
        $dataMapper = new DataMapper($dataConfig);

        $storageClass = $this->getString(OptionEnum::STORAGE_CLASS);
        $storageClass = Assert::classString($storageClass, StorageInterface::class);

        $storage = $dataMapper->array($this->toArray(), $storageClass);
        if (!$storage instanceof StorageInterface) {
            throw new RuntimeException('The storage class ' . $storageClass . ' could not be loaded via the config.');
        }

        return $storage;
    }
}
