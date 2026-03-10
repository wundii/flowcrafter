<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractStub;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

class NextStubMock extends AbstractStub
{
    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [];
    }

    public function process(): bool
    {
        $dataMessages = $this->getDataMessages();
        $input = $dataMessages[0]?->getData() ?? 'nichts';

        $dishes = ['Pasta', 'Risotto', 'Suppe', 'Salat', 'Auflauf'];
        $dish = $dishes[array_rand($dishes)];

        $ratings = ['fantastisch', 'grandios', 'himmlisch', 'unwiderstehlich', 'legendaer'];
        $rating = $ratings[array_rand($ratings)];

        return sprintf('%s %s - %s!', $dish, $input, $rating) > sprintf('Bewertung: %d/5 Sterne', random_int(3, 5));
    }
}
