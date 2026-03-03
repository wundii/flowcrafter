<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Tests\MockClass\MessageInitMock;
use Tests\MockClass\WorkflowMock;
use Tests\Trait\MySqlClientTestTrait;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Storage\MySql;

final class FlowRunnerMySqlTest extends TestCase
{
    use MySqlClientTestTrait;

    public function testRunReturnsMessageReturnInterface(): void
    {
        $mySql = new MySql(
            $this->container->getHost(),
            $this->container->getMappedPort(self::PORT),
            self::DATABASE,
            self::USERNAME,
            self::PASSWORD
        );
        $flowRunner = new FlowRunner(
            type: 'flow.workflow.v1',
            flowSource: WorkflowMock::class,
            storage: $mySql,
        );
        $flowRunner->run(new MessageInitMock('test data'));

        $this->assertCount(5, $flowRunner->getFlow()->getFlowMessages());

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_SCHEMA);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_INSTANCE);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_RUN);
        $this->assertCount(1, iterator_to_array($stmt->fetchAll()));

        $stmt = $this->client->query('SELECT * FROM ' . MySql::TYPE_MESSAGE);
        $this->assertCount(5, iterator_to_array($stmt->fetchAll()));
    }
}
