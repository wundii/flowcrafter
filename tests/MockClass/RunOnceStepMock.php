<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class RunOnceStepMock implements StepInterface
{
    private static int $callCount = 0;

    public function __construct(
        private readonly MessageDataMock $messageDataMock,
    ) {
    }

    public static function reset(): void
    {
        self::$callCount = 0;
    }

    public static function getCallCount(): int
    {
        return self::$callCount;
    }

    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [
            MessageReturnMock::class,
        ];
    }

    public function process(): MessageReturnInterface
    {
        ++self::$callCount;

        return new MessageReturnMock(
            data: 'run-once result: ' . $this->messageDataMock->getData(),
            test: 'call-' . self::$callCount,
        );
    }
}
