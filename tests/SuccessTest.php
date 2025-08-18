<?php

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

final class SuccessTest extends TestCase
{
    public function testSucceed(): void
    {
        $this->assertTrue(true);
    }
}
