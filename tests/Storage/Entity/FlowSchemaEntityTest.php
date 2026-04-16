<?php

declare(strict_types=1);

namespace Tests\Storage\Entity;

use PHPUnit\Framework\TestCase;
use Wundii\Flowcrafter\Storage\Entity\FlowSchemaEntity;

final class FlowSchemaEntityTest extends TestCase
{
    public function testConstructorAndPublicProperties(): void
    {
        $stubs = ['StubA', 'StubB'];
        $flowSchemaEntity = new FlowSchemaEntity(
            schemaHash: 'abc-123',
            type: 'flow.test.v1',
            stubs: $stubs,
        );

        $this->assertSame('abc-123', $flowSchemaEntity->schemaHash);
        $this->assertSame('flow.test.v1', $flowSchemaEntity->type);
        $this->assertSame($stubs, $flowSchemaEntity->stubs);
    }

    public function testJsonSerialize(): void
    {
        $stubs = ['StubX'];
        $flowSchemaEntity = new FlowSchemaEntity(
            schemaHash: 'hash-xyz',
            type: 'flow.example.v2',
            stubs: $stubs,
        );

        $this->assertSame([
            'schemaHash' => 'hash-xyz',
            'type' => 'flow.example.v2',
            'stubs' => $stubs,
        ], $flowSchemaEntity->jsonSerialize());
    }
}
