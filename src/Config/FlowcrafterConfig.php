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
        $this->setParameter(OptionEnum::STORAGE_URL, null);
        $this->setParameter(OptionEnum::STORAGE_APITOKEN, null);
        $this->setParameter(OptionEnum::STORAGE_HOST, null);
        $this->setParameter(OptionEnum::STORAGE_PORT, null);
        $this->setParameter(OptionEnum::STORAGE_USERNAME, null);
        $this->setParameter(OptionEnum::STORAGE_PASSWORD, null);
        $this->setParameter(OptionEnum::STORAGE_DATABASE, null);
    }

    public function setStorageClass(string $storageClass): void
    {
        $this->setParameter(OptionEnum::STORAGE_CLASS, $storageClass);
    }

    public function setStorageUrl(?string $storageUrl = null): void
    {
        $this->setParameter(OptionEnum::STORAGE_URL, $storageUrl);
    }

    public function setStorageApiToken(?string $storageApiToken = null): void
    {
        $this->setParameter(OptionEnum::STORAGE_APITOKEN, $storageApiToken);
    }

    public function setStorageHost(string $storageHost): void
    {
        $this->setParameter(OptionEnum::STORAGE_HOST, $storageHost);
    }

    public function setStoragePort(int $storagePort): void
    {
        $this->setParameter(OptionEnum::STORAGE_PORT, $storagePort);
    }

    public function setStorageUsername(?string $storageUsername = null): void
    {
        $this->setParameter(OptionEnum::STORAGE_USERNAME, $storageUsername);
    }

    public function setStoragePassword(?string $storagePassword = null): void
    {
        $this->setParameter(OptionEnum::STORAGE_PASSWORD, $storagePassword);
    }

    public function setStorageDatabase(?string $storageDatabase = null): void
    {
        $this->setParameter(OptionEnum::STORAGE_DATABASE, $storageDatabase);
    }

    public function setServerSecret(?string $serverSecret = null): void
    {
        $this->setParameter(OptionEnum::SERVER_SECRET, $serverSecret);
    }

    public function getServerSecret() : ?string
    {
        return $this->getNullOrString(OptionEnum::SERVER_SECRET);
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return [
            OptionEnum::STORAGE_URL->value => $this->getNullOrString(OptionEnum::STORAGE_URL),
            OptionEnum::STORAGE_APITOKEN->value => $this->getNullOrString(OptionEnum::STORAGE_APITOKEN),
            OptionEnum::STORAGE_HOST->value => $this->getNullOrString(OptionEnum::STORAGE_HOST),
            OptionEnum::STORAGE_PORT->value => $this->getNullOrInt(OptionEnum::STORAGE_PORT),
            OptionEnum::STORAGE_USERNAME->value => $this->getNullOrString(OptionEnum::STORAGE_USERNAME),
            OptionEnum::STORAGE_PASSWORD->value => $this->getNullOrString(OptionEnum::STORAGE_PASSWORD),
            OptionEnum::STORAGE_DATABASE->value => $this->getNullOrString(OptionEnum::STORAGE_DATABASE),
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
