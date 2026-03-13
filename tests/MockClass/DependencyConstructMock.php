<?php

declare(strict_types=1);

namespace Tests\MockClass;

class DependencyConstructMock
{
    public function __construct(
        public string $value,
    ) {
    }
}
