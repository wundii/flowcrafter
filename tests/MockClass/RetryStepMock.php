<?php

declare(strict_types=1);

namespace Tests\MockClass;

use RuntimeException;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StepInterface;

class RetryStepMock implements StepInterface
{
    private static int $callCount = 0;

    private static int $failUntil = 0;

    public function __construct(
        private readonly MessageDataMock $messageDataMock,
    ) {
    }

    public static function configure(int $failUntil): void
    {
        self::$failUntil = $failUntil;
        self::$callCount = 0;
    }

    public static function reset(): void
    {
        self::$failUntil = 0;
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

        if (self::$callCount <= self::$failUntil) {
            throw new RuntimeException('Retry attempt ' . self::$callCount);
        }

        return new MessageReturnMock(
            data: 'success after ' . self::$callCount . ' attempts: ' . $this->messageDataMock->getData(),
            test: (string) self::$failUntil,
        );
    }
}
