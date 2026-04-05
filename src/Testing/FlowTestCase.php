<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter\Testing;

use PHPUnit\Framework\TestCase;

abstract class FlowTestCase extends TestCase
{
    use FlowAssertTrait;
}
