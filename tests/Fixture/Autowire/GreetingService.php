<?php

declare(strict_types=1);

namespace Tests\Fixture\Autowire;

final class GreetingService
{
    public function greet(): string
    {
        return 'hello';
    }
}
