<?php

declare(strict_types=1);

namespace Tests\MockClass;

class DependencyMock
{
    public function strToUpper(string $value): string
    {
        return strtoupper($value);
    }
}
