<?php

declare(strict_types=1);

namespace Tests\Storage\Entity;

use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Storage\Entity\FlowSchemaEntity;

final class FlowSchemaEntityTest extends TestCase
{
    public function testConstructorAndPublicProperties(): void
    {
        $steps = ['StepA', 'StepB'];
        $flowSchemaEntity = new FlowSchemaEntity(
            schemaHash: 'abc-123',
            type: 'flow.test.v1',
            steps: $steps,
        );

        $this->assertSame('abc-123', $flowSchemaEntity->schemaHash);
        $this->assertSame('flow.test.v1', $flowSchemaEntity->type);
        $this->assertSame($steps, $flowSchemaEntity->steps);
    }

    public function testJsonSerialize(): void
    {
        $steps = ['StepX'];
        $flowSchemaEntity = new FlowSchemaEntity(
            schemaHash: 'hash-xyz',
            type: 'flow.example.v2',
            steps: $steps,
        );

        $this->assertSame([
            'schemaHash' => 'hash-xyz',
            'type' => 'flow.example.v2',
            'steps' => $steps,
        ], $flowSchemaEntity->jsonSerialize());
    }
}
