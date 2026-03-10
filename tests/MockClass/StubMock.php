<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractStub;
use Wundii\Flowcrafter\Interface\MessageDataInterface;

class StubMock extends AbstractStub
{
    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [
            MessageDataMock::class,
        ];
    }

    public function process(): MessageDataInterface
    {
        $initMessage = $this->getInitMessage();
        $input = $initMessage?->getData() ?? 'unknown';

        $ingredients = ['Tomaten', 'Knoblauch', 'Basilikum', 'Zwiebeln', 'Pilze', 'Paprika'];
        $ingredient = $ingredients[array_rand($ingredients)];

        return new MessageDataMock(sprintf('%s mit %s', $input, $ingredient));
    }
}
