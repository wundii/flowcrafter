<?php

declare(strict_types=1);

namespace Tests\Fixture\Autowire;

final class GreetingConsumer
{
    public function __construct(
        private readonly GreetingService $greetingService,
    ) {
    }

    public function message(): string
    {
        return $this->greetingService->greet() . ' world';
    }
}
