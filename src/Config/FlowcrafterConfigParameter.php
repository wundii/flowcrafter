<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Config;

use Wundii\Flowcrafter\Assert;

class FlowcrafterConfigParameter
{
    /**
     * @var array <string, mixed>
     */
    private array $parameters = [];

    public function has(OptionEnum $optionEnum): bool
    {
        return array_key_exists($optionEnum->value, $this->parameters);
    }

    public function getBoolean(OptionEnum $optionEnum): bool
    {
        $parameter = $this->getParameter($optionEnum);

        return Assert::bool($parameter);
    }

    public function getInteger(OptionEnum $optionEnum): int
    {
        $parameter = $this->getParameter($optionEnum);

        return Assert::int($parameter);
    }

    public function getString(OptionEnum $optionEnum): string
    {
        $parameter = $this->getParameter($optionEnum);

        return Assert::string($parameter);
    }

    public function getNullOrString(OptionEnum $optionEnum): ?string
    {
        $parameter = $this->getParameter($optionEnum);

        return Assert::nullOrString($parameter);
    }

    public function getNullOrInt(OptionEnum $optionEnum): ?int
    {
        $parameter = $this->getParameter($optionEnum);

        return Assert::nullOrInt($parameter);
    }

    public function getNullOrFloat(OptionEnum $optionEnum): ?float
    {
        $parameter = $this->getParameter($optionEnum);

        return Assert::nullOrFloat($parameter);
    }

    /**
     * @return string[]
     */
    public function getArrayWithStrings(OptionEnum $optionEnum): array
    {
        $parameter = $this->getParameter($optionEnum, []);

        return Assert::allString($parameter);
    }

    public function getParameter(OptionEnum $optionEnum, mixed $default = null): mixed
    {
        return $this->parameters[$optionEnum->value] ?? $default;
    }

    protected function setParameter(OptionEnum $optionEnum, mixed $value): void
    {
        $this->parameters[$optionEnum->value] = $value;
    }
}
