<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class PostStubMock implements StubInterface
{
    public function __construct(
        private readonly MessageDataMock $messageDataMock,
        private readonly MessageDataSecondMock $messageDataSecondMock,
        private readonly ?DependencyMock $dependencyMock = null,
        private readonly ?DependencyConstructMock $dependencyConstructMock = null,
    ) {
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
        $parts = [
            $this->messageDataMock->getData(),
            $this->messageDataSecondMock->getData(),
        ];

        $summary = implode(' | ', $parts);
        $summary .= $this->dependencyConstructMock?->value;

        $emojis = ['Chef-Kuss', 'Meisterwerk', 'Sterneküche', 'Gaumenschmaus', 'Kulinarik-Genie'];
        $emoji = $emojis[array_rand($emojis)];
        $data = sprintf('[%s] %s', $emoji, $summary);
        $data = $this->dependencyMock?->strToUpper($data) ?? $data;

        return new MessageReturnMock(
            $data,
            sprintf('Zubereitet in %d Minuten', random_int(5, 45)),
        );
    }
}
