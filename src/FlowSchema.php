<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use JsonSerializable;
use RuntimeException;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

readonly class FlowSchema implements JsonSerializable
{
    /**
     * @param Stub[] $stubs
     */
    public function __construct(
        private string $type,
        private array $stubs,
    ) {
    }

    /**
     * @param class-string<FlowInterface> $class
     */
    public static function create(string $class): self
    {
        Assert::classString(
            $class,
            FlowInterface::class,
            sprintf('Class "%s" does not implement FlowInterface.', $class),
        );

        return $class::schema();
    }

    public function type(): string
    {
        return $this->type;
    }

    public function initStub(): Stub
    {
        $stubs = array_filter(
            $this->stubs,
            static fn (Stub $stub): bool => $stub->getMessageEnum() === MessageEnum::INIT,
        );

        return reset($stubs) ?: throw new RuntimeException('No INIT stub found in the schema.');
    }

    /**
     * @return Stub[]
     */
    public function stubs(): array
    {
        return $this->stubs;
    }

    /**
     * @return Stub[]
     */
    public function stubByMessageClass(string $messageClass): array
    {
        Assert::classString(
            $messageClass,
            MessageInterface::class,
            sprintf('Class "%s" does not implement MessageInterface.', $messageClass),
        );

        $stubs = array_filter(
            $this->stubs,
            static fn (Stub $stub): bool => in_array($messageClass, $stub->getMessages(), true)
        );

        return array_values($stubs);
    }

    /**
     * @param class-string<StubInterface> $stubSource
     */
    public function stubBySource(string $stubSource): Stub
    {
        Assert::classString(
            $stubSource,
            StubInterface::class,
            sprintf('Class "%s" does not implement StubInterface.', $stubSource),
        );

        foreach ($this->stubs as $stub) {
            if ($stub->getSource() === $stubSource) {
                return $stub;
            }
        }

        throw new RuntimeException(sprintf('No stub found with source "%s".', $stubSource));
    }

    /**
     * @return array<class-string<MessageInterface>, Stub[]>
     */
    public function getMessageToSubsMap(): array
    {
        $map = [];

        foreach ($this->stubs as $stub) {
            foreach ($stub->getMessages() as $messageClass) {
                $map[$messageClass][] = $stub;
            }
        }

        return $map;
    }

    /**
     * @return class-string[]
     */
    public function getClassStrings(): array
    {
        $objects = [];

        foreach ($this->stubs as $stub) {
            $objects[] = $stub->getSource();
            // foreach ($stub->getMessages() as $messageClass) {
            //     $objects[] = $messageClass;
            // }
        }

        return $objects;
    }

    public function getHash(): string
    {
        $json = json_encode($this->jsonSerialize());
        if ($json === false) {
            throw new RuntimeException('Failed to encode flow schema to JSON.');
        }

        return md5($json);
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'stubs' => $this->stubs,
        ];
    }
}
