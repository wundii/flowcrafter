<?php

declare(strict_types=1);

namespace Tests\Trait;

use Thenativeweb\Eventsourcingdb\Client;
use Thenativeweb\Eventsourcingdb\Container;

trait EsdbClientTestTrait
{
    private Container $container;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->startContainer();
        $this->client = $this->container->getClient();
    }

    protected function tearDown(): void
    {
        $this->container->stop();
        parent::tearDown();
    }

    protected function startContainer(): Container
    {
        $container = (new Container())->withImageTag('latest');
        $container->start();

        return $container;
    }
}
