<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractStub;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

class PostStubMock extends AbstractStub
{
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
        $dataMessages = $this->getDataMessages();
        $parts = [];
        foreach ($dataMessages as $message) {
            $parts[] = $message->getData();
        }

        $summary = implode(' | ', $parts);

        $emojis = ['Chef-Kuss', 'Meisterwerk', 'Sterneküche', 'Gaumenschmaus', 'Kulinarik-Genie'];
        $emoji = $emojis[array_rand($emojis)];

        return new MessageReturnMock(
            sprintf('[%s] %s', $emoji, $summary),
            sprintf('Zubereitet in %d Minuten', random_int(5, 45)),
        );
    }
}
