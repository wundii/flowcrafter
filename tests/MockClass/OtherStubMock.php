<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\AbstractStub;
use Wundii\Flowcrafter\Interface\MessageDataInterface;

class OtherStubMock extends AbstractStub
{
    /**
     * @return class-string[]
     */
    public function returnTypes(): array
    {
        return [
            MessageDataSecondMock::class,
        ];
    }

    public function process(): MessageDataInterface
    {
        $dataMessages = $this->getDataMessages();
        $input = $dataMessages[0]?->getData() ?? 'nichts';

        $methods = ['gebraten', 'gegrillt', 'gedaempft', 'flammbiert', 'mariniert'];
        $method = $methods[array_rand($methods)];

        $extras = ['einer Prise Liebe', 'einem Hauch Magie', 'extra Kaese', 'frischen Kraeutern', 'Geheimgewuerz'];
        $extra = $extras[array_rand($extras)];

        return new MessageDataSecondMock(sprintf('%s, %s mit %s', $input, $method, $extra));
    }
}
