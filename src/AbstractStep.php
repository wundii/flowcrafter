<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use Wundii\Flowcrafter\Interface\StepInterface;

abstract class AbstractStep implements StepInterface
{
    private string $flowHash;

    private string $flowRuntimeHash;

    private string $flowType;

    private string $flowSchemaHash;

    private ?string $flowSubject;

    public function setAbstractData(
        string $flowHash,
        string $flowRuntimeHash,
        string $flowType,
        string $flowSchemaHash,
        ?string $flowSubject,
    ): void {
        $this->flowHash = $flowHash;
        $this->flowRuntimeHash = $flowRuntimeHash;
        $this->flowType = $flowType;
        $this->flowSchemaHash = $flowSchemaHash;
        $this->flowSubject = $flowSubject;
    }

    public function getFlowHash(): string
    {
        return $this->flowHash;
    }

    public function getFlowRuntimeHash(): string
    {
        return $this->flowRuntimeHash;
    }

    public function getFlowType(): string
    {
        return $this->flowType;
    }

    public function getFlowSchemaHash(): string
    {
        return $this->flowSchemaHash;
    }

    public function getFlowSubject(): ?string
    {
        return $this->flowSubject;
    }
}
