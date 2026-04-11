# flowcrafter

[![PHP-Tests](https://img.shields.io/github/actions/workflow/status/wundii/flowcrafter/code_quality.yml?branch=main&style=for-the-badge)](https://github.com/wundii/flowcrafter/actions/workflows/code_quality.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%2010-brightgreen.svg?style=for-the-badge)](https://phpstan.org/)
![VERSION](https://img.shields.io/packagist/v/wundii/flowcrafter?style=for-the-badge)
[![PHP](https://img.shields.io/packagist/php-v/wundii/flowcrafter?style=for-the-badge)](https://www.php.net/)
[![Rector](https://img.shields.io/badge/Rector-8.2-blue.svg?style=for-the-badge)](https://getrector.com)
[![ECS](https://img.shields.io/badge/ECS-check-blue.svg?style=for-the-badge)](https://tomasvotruba.com/blog/zen-config-in-ecs)
[![PHPUnit](https://img.shields.io/badge/PHP--Unit-check-blue.svg?style=for-the-badge)](https://phpunit.org)
[![codecov](https://img.shields.io/codecov/c/github/wundii/flowcrafter/main?token=TNC2MM0MWS&style=for-the-badge)](https://codecov.io/github/wundii/flowcrafter)
[![Downloads](https://img.shields.io/packagist/dt/wundii/flowcrafter.svg?style=for-the-badge)](https://packagist.org/packages/wundii/flowcrafter)

PHP-Engine für message-driven Workflows — Schema-as-Code,
typsicheres Routing über Message-Klassen, synchrone und asynchrone
Ausführung mit vollständigem Audit-Log.

## Features

- Typsichere Workflow-Definitionen als PHP-Klassen — kein YAML/XML
- Storage-Backends: **MySQL**, **Redis**, **EventSourcingDB** mit
  SQLite-Service-Layer als Query-Cache — eigene Backends via
  `StorageInterface` frei erweiterbar
- Synchrone Ausführung (`FlowRunner`) + asynchrone Queue-Verarbeitung
  (`FlowObserver`) + zeitgesteuerte Ausführung (`FlowScheduler`)
- Automatischer Flow-Status, vollständiges Message-, Exception- &
  Schedule-Exception-Logging, Schema-Versionierung via Hash
- `#[FlowGroup]`- und `#[FlowSchedule(group:)]`-Attribute für
  UI-Gruppierung von Flow-Typen und Schedules
- UI-DevTool wird automatisch aktiviert wenn der Server via `bin/flowcrafter dev` gestartet wird
- REST-API für Flows, Schemas, Queues, Exceptions & Schedule-Exceptions
  inkl. Prometheus/OpenMetrics-Endpunkt;
- Symfony Console Commands für Config, Storage-Init/Rebuild, Dev-Server,
  Observer, Scheduler und Mermaid-Diagramme
- Testing-Helper (`FlowTestCase`, `FlowAssertTrait`) für storageless
  Unit-Tests

## Installation

```bash
composer require wundii/flowcrafter
```

## Dokumentation

Die vollständige Dokumentation liegt im [`docs/`](docs/)-Ordner:

| Kapitel | Inhalt |
|---|---|
| [Console Commands](docs/commands.md) | Command-Referenz |
| [Deployment](docs/deployment.md) | Produktion: FrankenPHP + Docker |
| [Entwicklung](docs/development.md) | QA-Scripts für Contributor |
| [Getting Started](docs/getting-started.md) | Erste Schritte: Config, Storage, Dev-Server |
| [Konfiguration](docs/configuration.md) | `flowcrafter.php`, Storage-Backends, Server-Einstellungen |
| [Konzepte](docs/concepts.md) | Flow, Status, Schema, Messages, includeStubs, Observer |
| [Monitoring](docs/monitoring.md) | Prometheus / OpenMetrics, CheckMK |
| [REST-API](docs/api.md) | Endpunkte, Pagination, Auth |
| [Testing](docs/testing.md) | Flows & Stubs testen mit PHPUnit 11+ |

## Quickstart

```bash
# 1. Config-Datei erzeugen
vendor/bin/flowcrafter config:create

# 2. Storage initialisieren
vendor/bin/flowcrafter storage:init

# 3. Dev-Server (API + Observer + Scheduler) starten
vendor/bin/flowcrafter dev
```

Details siehe [docs/getting-started.md](docs/getting-started.md).

## Web-UI

Das optionale Web-Frontend
[FlowCrafter UI](https://github.com/wundii/flowcrafter-ui) visualisiert
Flows, Messages, Exceptions, Schedules und Queues in Echtzeit:

```bash
docker run -p 5173:5173 -v ./data:/flowcrafter/data wundii/flowcrafter-ui:latest
```
<p align="center">
  <picture>
    <img src="https://raw.githubusercontent.com/wundii/flowcrafter-ui/refs/heads/main/assets/screen_01.png" alt="wundii/flowcrafter" style="width: 100%; max-width: 600px; height: auto;">
  </picture>
</p>

## Minimalbeispiel

### Messages
readonly Value-Objects. Drei Typen: `Init` startet den Flow, `Data` fließt zwischen Stubs, `Return` beendet den Flow:

```php
use Wundii\Flowcrafter\AbstractMessage;
use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;

readonly class OrderInit extends AbstractMessage implements MessageInitInterface
{
    public function __construct(private string $sku) {}
    public function getSku(): string { return $this->sku; }
}

readonly class OrderValidated extends AbstractMessage implements MessageDataInterface
{
    public function __construct(private string $sku, private int $quantity) {}
    public function getSku(): string { return $this->sku; }
    public function getQuantity(): int { return $this->quantity; }
}

readonly class OrderCompleted extends AbstractMessage implements MessageReturnInterface
{
    public function __construct(private string $summary) {}
    public function getSummary(): string { return $this->summary; }
}
```

Braucht der erste Stub keinen externen Input, kann statt einer eigenen Init-Klasse die mitgelieferte
`Wundii\Flowcrafter\EmptyInitMessage` verwendet werden. Damit Rector den Konstruktor-Parameter nicht als
ungenutzt entfernt, wird sie als `public readonly` promoted Property deklariert:

```php
use Wundii\Flowcrafter\EmptyInitMessage;

class StartStub implements StubInterface
{
    public function __construct(
        public readonly EmptyInitMessage $init,
    ) {}

    /** @return class-string[] */
    public function returnTypes(): array { return [OrderValidated::class]; }

    public function process(): MessageDataInterface
    {
        return new OrderValidated('SKU-1', quantity: 1);
    }
}
```

### Stubs
reine PHP-Klassen. Der Constructor-Typ entscheidet das Routing. Ein Stub kann `MessageData` (→ Flow läuft weiter), `MessageReturn`
(→ Flow endet) oder `bool` (→ Leaf-Result) zurückgeben:

```php
use Wundii\Flowcrafter\Interface\MessageDataInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

// Zwischenschritt: Init → Data
class ValidateStub implements StubInterface
{
    public function __construct(private readonly OrderInit $init) {}

    /** @return class-string[] */
    public function returnTypes(): array { return [OrderValidated::class]; }

    public function process(): MessageDataInterface
    {
        return new OrderValidated($this->init->getSku(), quantity: 1);
    }
}

// Haupt-Branch: Data → Return (beendet den Flow)
class CompleteOrderStub implements StubInterface
{
    public function __construct(private readonly OrderValidated $validated) {}

    /** @return class-string[] */
    public function returnTypes(): array { return [OrderCompleted::class]; }

    public function process(): MessageReturnInterface
    {
        return new OrderCompleted(sprintf(
            'Order %s x%d completed',
            $this->validated->getSku(),
            $this->validated->getQuantity(),
        ));
    }
}

// Leaf-Stub: Data → bool (FlowResult, kein Weiterleiten)
class AuditStub implements StubInterface
{
    public function __construct(private readonly OrderValidated $validated) {}

    /** @return class-string[] */
    public function returnTypes(): array { return []; }

    public function process(): bool
    {
        return $this->validated->getQuantity() > 0;
    }
}
```

### Flow
Schema via `FlowBuilder`, kein YAML. Zwei Stubs konsumieren `OrderValidated` parallel.

Optional kann ein Flow mit `#[FlowGroup]` einer UI-Gruppe zugeordnet werden — beeinflusst den Schema-Hash nicht:

```php
use Wundii\Flowcrafter\Attribute\FlowGroup;

#[FlowGroup('Order Management')]
class OrderFlow implements FlowInterface { ... }
```

```php
use Wundii\Flowcrafter\FlowBuilder;
use Wundii\Flowcrafter\FlowSchema;
use Wundii\Flowcrafter\Interface\FlowInterface;

class OrderFlow implements FlowInterface
{
    public static function schema(): FlowSchema
    {
        $builder = new FlowBuilder('flow.order.v1', OrderInit::class, OrderCompleted::class);
        $builder->addStub(ValidateStub::class);
        $builder->addStub(CompleteOrderStub::class);
        $builder->addStub(AuditStub::class);
        return $builder->build();
    }
}
```

### Flow-Diagramm
automatisch aus dem Schema generierbar via `vendor/bin/flowcrafter diagram:mermaid App\\OrderFlow`:

```mermaid
---
title: flow.order.v1
theme: neo
---
stateDiagram-v2
[*]-->ValidateStub: OrderInit
ValidateStub-->CompleteOrderStub: OrderValidated
ValidateStub-->AuditStub: OrderValidated
CompleteOrderStub-->[*]: OrderCompleted
```

### Flow auslösen
Zwei Wege: **synchron** im eigenen Code via `FlowRunner` oder **asynchron** über die Queue (vom `FlowObserver` abgearbeitet).

**Synchron** — direkter Aufruf, Ergebnis sofort verfügbar:

```php
use Wundii\Flowcrafter\FlowRunner;

$flowRunner = new FlowRunner(
    type: 'flow.order.v1',
    flowSource: OrderFlow::class,
    flowSubject: 'sku-42',          // optional, Geschäfts-Key zur späteren Suche
    storage: $storage,              // aus $flowcrafterConfig->getStorage()
);

$result = $flowRunner->run(new OrderInit('sku-42'));
// $result ist MessageReturnInterface|bool — hier: OrderCompleted
```

**Asynchron** — Message in die Queue legen, der `FlowObserver`-Worker führt sie aus:

```php
$storage->appendObserveItem(
    type: 'flow.order.v1',
    flowSource: OrderFlow::class,
    flowHash: null,                 // null = neuer Flow, sonst Re-Run einer bestehenden Instanz
    messageSource: OrderInit::class,
    message: (new OrderInit('sku-42'))->jsonSerialize(),
    flowSubject: 'sku-42',
);
```

Alternativ über die REST-API: `POST /api/flows/run` (synchron) bzw. `POST /api/queue` (async) — siehe [docs/api.md](docs/api.md).

**Zeitgesteuert** — Schedule-Klasse mit Cron-Ausdruck, wird automatisch vom `FlowScheduler` entdeckt und ausgeführt:

```php
use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;

#[FlowSchedule('0 */6 * * *', name: 'order-cleanup', group: 'Maintenance')]
class OrderCleanupSchedule extends AbstractSchedule
{
    public function process(): void
    {
        $this->enqueue(OrderFlow::class, new OrderInit('scheduled-cleanup'));
        // oder synchron: $this->run(OrderFlow::class, new OrderInit('cleanup'));
    }
}
```

Schedule-Klassen werden über das `#[FlowSchedule]`-Attribut automatisch aus dem Composer-Classmap entdeckt — keine manuelle Registrierung nötig. Der Scheduler läuft als eigenständiger Prozess (`vendor/bin/flowcrafter scheduler`) oder im Dev-Modus inline mit.

### Test
storageless mit `FlowTestCase`, kein Docker nötig:

```php
use Wundii\Flowcrafter\Testing\FlowTestCase;

final class OrderFlowTest extends FlowTestCase
{
    public function testHappyPath(): void
    {
        $this->runFlow(
            flowType: 'flow.order.v1',
            flowSource: OrderFlow::class,
            initMessage: new OrderInit('sku-42'),
        );

        $this->assertFlowOk();
        $this->assertStubExecuted(ValidateStub::class);
        $this->assertStubExecuted(CompleteOrderStub::class);
        $this->assertStubExecuted(AuditStub::class);
        $this->assertFlowHasMessage(OrderValidated::class);
        $this->assertFlowBoolResult(true);   // AuditStub lieferte true

        $return = $this->assertFlowReturned(OrderCompleted::class);
        $this->assertSame('Order sku-42 x1 completed', $return->getSummary());
    }
}
```

Vollständiger Testing-Leitfaden: [docs/testing.md](docs/testing.md).

## Lizenz

MIT — siehe [LICENCE](LICENCE).
