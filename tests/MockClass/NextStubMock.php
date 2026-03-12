<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\StubInterface;

class NextStubMock implements StubInterface
{
    public function __construct(
        private readonly MessageDataMock $messageDataMock,
    ) {
    }

    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [];
    }

    public function process(): bool
    {
        $input = $this->messageDataMock->getData();

        $dishes = ['Pasta', 'Risotto', 'Suppe', 'Salat', 'Auflauf'];
        $dish = $dishes[array_rand($dishes)];

        $ratings = ['fantastisch', 'grandios', 'himmlisch', 'unwiderstehlich', 'legendaer'];
        $rating = $ratings[array_rand($ratings)];

        return sprintf('%s %s - %s!', $dish, $input, $rating) > sprintf('Bewertung: %d/5 Sterne', random_int(3, 5));
    }
}
