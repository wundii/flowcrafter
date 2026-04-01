<?php

declare(strict_types=1);

namespace Tests\MockClass;

use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class OtherStubMock implements StubInterface
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
        return [
            MessageDataSecondMock::class,
        ];
    }

    public function process(): MessageDataInterface
    {
        $input = $this->messageDataMock->getData();

        $methods = ['gebraten', 'gegrillt', 'gedaempft', 'flammbiert', 'mariniert'];
        $method = $methods[array_rand($methods)];

        $extras = ['einer Prise Liebe', 'einem Hauch Magie', 'extra Kaese', 'frischen Kraeutern', 'Geheimgewuerz'];
        $extra = $extras[array_rand($extras)];

        return new MessageDataSecondMock(sprintf('%s, %s mit %s', $input, $method, $extra), new MessageSubDataMock('alien'));
    }
}
