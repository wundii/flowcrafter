# Testing
- [Back to README.md](./../README.md)

Flows, Stubs und Messages in Flowcrafter sind von Grund auf so entworfen,
dass sie sich mit **PHPUnit 11+** sauber testen lassen — ohne Mocks, ohne
Docker, ohne Datenbank. Dieser Leitfaden zeigt, wie du deine eigenen Flows
testest.

## Inhalt

- [Warum Flows gut testbar sind](#warum-flows-gut-testbar-sind)
- [Test-Ebenen im Überblick](#test-ebenen-im-überblick)
- [Schnellstart: FlowTestCase](#schnellstart-flowtestcase)
- [runFlow() — kompletter Flow-Durchlauf](#runflow--kompletter-flow-durchlauf)
- [runStub() — Stub isoliert testen](#runstub--stub-isoliert-testen)
- [Assertions im Detail](#assertions-im-detail)
- [Dependency Injection im Test](#dependency-injection-im-test)
- [Fehlerpfade und Exceptions testen](#fehlerpfade-und-exceptions-testen)
- [Teilweise Ausführung mit includeStubs](#teilweise-ausführung-mit-includestubs)
- [Mehrere Flows in einem Test](#mehrere-flows-in-einem-test)
- [FlowAssertTrait in eigene Basisklassen einbinden](#flowasserttrait-in-eigene-basisklassen-einbinden)
- [Storage-Integrationstests](#storage-integrationstests)

---

## Warum Flows gut testbar sind

| Eigenschaft | Konsequenz für Tests |
|---|---|
| `FlowRunner` akzeptiert `StorageInterface` optional (`null`) | Komplette Flow-Ausführung **ohne DB, ohne Docker** |
| Messages sind `readonly` Value-Objects | Triviale Konstruktion und `assertEquals`-vergleichbar |
| Stubs haben Constructor-DI mit Typen | Autowiring — Fakes lassen sich direkt injizieren |
| Flow-Objekt exponiert den kompletten Lifecycle | Routing, Fan-In, Exceptions und Results sind introspektierbar |
| `FlowBuilder::build()` validiert beim Bauen | Schema-Fehler fallen schon beim `MyFlow::schema()`-Aufruf auf |

---

## Test-Ebenen im Überblick

| Ebene | Werkzeug | Geschwindigkeit | Braucht Docker |
|---|---|---|---|
| Schema-Validierung | `MyFlow::schema()` direkt aufrufen | ms | nein |
| Stub isoliert | `runStub()` oder `new Stub(...)` | ms | nein |
| Flow end-to-end (in-memory) | `runFlow()` bzw. `FlowRunner` ohne Storage | ms–10ms | nein |
| Branch- / Fehlerpfade | `runFlow()` + `includeStubs` + Fake-Dependencies | ms | nein |
| Storage-Integration | Testcontainers (MySQL / Redis / EsDB) | Sekunden | ja |

**Empfehlung:** 90% der Tests laufen ohne Docker. Storage-Integrationstests
sind nur nötig, wenn du einen eigenen `StorageInterface`-Adapter schreibst.

---

## Schnellstart: FlowTestCase

Flowcrafter liefert mit `Wundii\Flowcrafter\Testing\FlowTestCase` eine
Abstract-Klasse aus, die auf PHPUnits `TestCase` aufsetzt und den Trait
`FlowAssertTrait` einbindet.

```php
<?php

declare(strict_types=1);

namespace App\Tests;

use App\Flow\OrderFlow;
use App\Message\OrderCompleted;
use App\Message\OrderInit;
use App\Stub\ValidateStub;
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
        $return = $this->assertFlowReturned(OrderCompleted::class);
        $this->assertSame('sku-42', $return->getSku());
    }
}
```

Kein Setup, kein `setUp()`-Boilerplate, keine Fixtures. Die Methode
`runFlow()` baut intern einen `FlowRunner` **ohne** Storage, führt den
Flow aus und merkt sich das resultierende `Flow`-Objekt für alle weiteren
Assertions.

---

## runFlow() — kompletter Flow-Durchlauf

```php
protected function runFlow(
    string $flowType,
    string $flowSource,
    MessageInterface $initMessage,
    ?string $flowSubject = null,
    array $dependencies = [],
    array $includeStubs = [],
): bool|MessageReturnInterface;
```

| Parameter | Zweck |
|---|---|
| `flowType` | Der Typ wie in `schema()` verwendet, z. B. `'flow.order.v1'` |
| `flowSource` | Die `FlowInterface`-Klasse |
| `initMessage` | Die Start-Message (`MessageInitInterface`) |
| `flowSubject` | Optionaler Geschäfts-Key (z. B. Order-ID), zur späteren Suche |
| `dependencies` | Services, die in Stubs autowired werden (siehe [Dependency Injection im Test](#dependency-injection-im-test)) |
| `includeStubs` | Nur diese Stubs ausführen (siehe [Teilweise Ausführung](#teilweise-ausführung-mit-includestubs)) |

Nach dem Aufruf stehen dir zwei Hilfsmethoden zur Verfügung:

```php
protected function lastFlow(): Flow;               // das ausgeführte Flow-Objekt
protected function lastResult(): bool|MessageReturnInterface;  // der Rückgabewert
```

---

## runStub() — Stub isoliert testen

Wenn du einen **einzelnen** Stub ohne den ganzen Flow-Graph testen willst
(z. B. weil er komplexe DI-Abhängigkeiten hat), nutze `runStub()`:

```php
protected function runStub(
    string $stubSource,
    array $messages,
    array $dependencies = [],
): bool|MessageInterface;
```

Intern baut der Helper einen Symfony `ContainerBuilder` mit derselben
Autowire-Logik wie `FlowRunner`, registriert deine Messages als
Synthetic-Services und ruft `$stub->process()` auf. **Kein Flow, keine
Schema-Validierung, kein Message-Lifecycle.**

```php
public function testValidateStubIsolated(): void
{
    $result = $this->runStub(
        stubSource: ValidateStub::class,
        messages: [new OrderInit('sku-42')],
        dependencies: [new FakeHttpClient()],
    );

    $this->assertInstanceOf(OrderValidated::class, $result);
}
```

**Wann `runStub()` statt `new MyStub(...)`?**

| Situation | Empfehlung |
|---|---|
| Stub hat nur Message-Parameter im Constructor | `new MyStub(new Init('x'))` — expliziter |
| Stub hat 2+ zusätzliche Services (`HttpClient`, `Repository`, `Logger`) | `runStub()` — spart den manuellen Wireup |
| Service soll autowired werden (kein `new`) | `runStub()` übernimmt Autowiring |

---

## Assertions im Detail

Alle Assertions arbeiten per Default auf dem **zuletzt** per `runFlow()`
ausgeführten Flow. Optional kann ein Flow explizit übergeben werden
(für Mehrfach-Flow-Tests oder eigenen `FlowRunner`-Code).

### Status-Assertions

```php
$this->assertFlowOk();                              // StatusEnum::OK
$this->assertFlowFailed();                          // StatusEnum::FAILED
$this->assertFlowStatus(StatusEnum::WARNING);       // beliebiger Status
```

Die berechnete Status-Logik (`Flow::status()`):

| Status | Bedingung |
|---|---|
| `IN_PROGRESS` | Leaf-Stubs noch nicht erreicht |
| `IN_PROGRESS_EXCEEDED` | wie oben, aber Run > 1h alt |
| `OK` | Alle Leafs erreicht, alle FlowResults `true` |
| `WARNING` | Alle Leafs erreicht, mindestens ein FlowResult `false` |
| `FAILED` | Exception im letzten Run |

### Return-Message prüfen

```php
$return = $this->assertFlowReturned(OrderCompleted::class);
$this->assertSame('sku-42', $return->getSku());
```

Gibt die gecastete `MessageReturnInterface`-Instanz zurück — praktisch für
Folge-Assertions auf Feldern.

### Stub-Ausführung prüfen

```php
$this->assertStubExecuted(ValidateStub::class);
$this->assertStubNotExecuted(SendEmailStub::class);
```

### Message-Inhalte prüfen

```php
$this->assertFlowHasMessage(OrderValidated::class);  // nutzt Flow::hasMessage()
$this->assertFlowMessageCount(6);
```

### Bool-Ergebnisse (Leaf-Stubs)

```php
$this->assertFlowBoolResult(true);   // alle FlowResults sind true
$this->assertFlowResultCount(2);
```

### Exceptions

```php
$this->assertNoFlowExceptions();
$this->assertFlowExceptionFrom(ValidateStub::class);
$this->assertFlowExceptionFrom(ValidateStub::class, 'sku required');
```

Der zweite Parameter prüft per `str_contains()` auf die Exception-Message.

### Runs (für Restart-Tests)

```php
$this->assertFlowRunCount(1);
```

### Der optionale `?Flow $flow`-Parameter

```php
$this->assertFlowOk();              // operiert auf lastFlow()
$this->assertFlowOk($anotherFlow);  // operiert auf $anotherFlow
```

Das erlaubt Assertions auf Flows, die du **selbst** gebaut hast:

```php
$runner = new FlowRunner(type: '...', flowSource: MyFlow::class);
$runner->run(new MyInit('x'));
$this->assertFlowOk($runner->getFlow());
```

---

## Dependency Injection im Test

Stubs, die zusätzliche Services brauchen (nicht nur Messages), bekommen
diese per Constructor autowired. Im Produktivcode via
`FlowcrafterConfig::$dependenciesInjection`, im Test via den
`dependencies`-Parameter:

```php
class ValidateStub implements StubInterface
{
    public function __construct(
        private readonly OrderInit $init,
        private readonly HttpClientInterface $http,
        private readonly InventoryRepository $inventory,
    ) {}

    public function process(): MessageDataInterface { /* ... */ }
    public function returnTypes(): array { return [OrderValidated::class]; }
}
```

Im Test:

```php
$this->runFlow(
    flowType: 'flow.order.v1',
    flowSource: OrderFlow::class,
    initMessage: new OrderInit('sku-42'),
    dependencies: [
        new FakeHttpClient(),                    // fertige Instanz
        InventoryRepository::class,              // wird autowired
    ],
);
```

**Faustregel:**
- **Fertiges Objekt** übergeben → wenn du das Verhalten kontrollierst (Fake, Spy, Stub).
- **Klassenname** übergeben → wenn der Symfony-Container die Dependency selbst bauen soll.

---

## Fehlerpfade und Exceptions testen

Wenn ein Stub wirft, **rethrow** `FlowRunner::run()` nach dem Persistieren
der `FlowException` auf dem Flow. Im Test fängst du die Exception ab und
assertierst anschließend auf dem Flow:

```php
public function testValidationFailsOnEmptySku(): void
{
    try {
        $this->runFlow(
            flowType: 'flow.order.v1',
            flowSource: OrderFlow::class,
            initMessage: new OrderInit(''),
        );
        self::fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $e) {
        $this->assertStringStartsWith('sku', $e->getMessage());
    }

    // Der Flow ist auch nach dem Rethrow verfügbar:
    $this->assertFlowFailed();
    $this->assertFlowExceptionFrom(ValidateStub::class, 'sku required');
}
```

`runFlow()` nutzt intern `try/finally`, damit `lastFlow()` auch nach einer
geworfenen Exception befüllt ist.

---

## Teilweise Ausführung mit includeStubs

`FlowRunner` unterstützt selektive Stub-Ausführung — nützlich für
**Branch-Tests** oder um einen konkreten Sub-Pfad isoliert zu prüfen:

```php
$this->runFlow(
    flowType: 'flow.order.v1',
    flowSource: OrderFlow::class,
    initMessage: new OrderInit('sku-42'),
    includeStubs: [ValidateStub::class],   // Rest wird übersprungen
);

$this->assertStubExecuted(ValidateStub::class);
$this->assertStubNotExecuted(ChargeStub::class);
```

Nicht gelistete Stubs werden vom Runner übersprungen — auch wenn sie
laut Schema eigentlich dran wären. `includeStubs: []` (Default) führt alle
Stubs aus.

---

## Mehrere Flows in einem Test

Willst du zwei Flows parallel prüfen (z. B. Vorher/Nachher), übergib das
Flow-Objekt explizit:

```php
$this->runFlow('flow.order.v1', OrderFlow::class, new OrderInit('a'));
$firstFlow = $this->lastFlow();

$this->runFlow('flow.order.v1', OrderFlow::class, new OrderInit('b'));
// lastFlow() ist jetzt der zweite Flow

$this->assertFlowOk($firstFlow);
$this->assertFlowOk();              // der zweite (implizit über lastFlow())
```

---

## FlowAssertTrait in eigene Basisklassen einbinden

Wenn du schon eine eigene Test-Basisklasse hast (z. B.
`Symfony\Bundle\FrameworkBundle\Test\KernelTestCase`), nutze den **Trait**
direkt statt `FlowTestCase`:

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wundii\Flowcrafter\Testing\FlowAssertTrait;

abstract class IntegrationTestCase extends KernelTestCase
{
    use FlowAssertTrait;
}
```

Der Trait setzt lediglich voraus, dass die nutzende Klasse von PHPUnits
`TestCase` erbt (damit `PHPUnit\Framework\Assert` bereitsteht).

---

## Storage-Integrationstests

Die Unit-Testing-Helper oben arbeiten **ohne** Storage — perfekt für
Anwender-Flows. Wenn du aber einen **eigenen** `StorageInterface`-Adapter
schreibst, musst du dessen Persistenz gegen ein echtes Backend testen:

- Flowcrafter verwendet intern **Testcontainers** (`testcontainers/testcontainers`)
  zum Hochfahren echter MySQL-, Redis- und EventSourcingDB-Container pro Test.
- Die Trait-Helfer `tests/Trait/MySqlClientTestTrait.php`,
  `RedisClientTestTrait.php` und `EsdbClientTestTrait.php` zeigen das Muster.
- Docker muss laufen; die Tests sind dadurch deutlich langsamer (Sekunden
  statt Millisekunden).

**Für Flowcrafter-Anwender** ist das in aller Regel **nicht** nötig:
dein Produktivcode injiziert den fertig konfigurierten Flowcrafter-Storage,
deine Flow-Logik wird in-memory getestet.

---

## Zusammenfassung

| Du willst … | Nutze |
|---|---|
| …den ganzen Flow durchspielen | `$this->runFlow(...)` |
| …einen einzelnen Stub mit DI testen | `$this->runStub(...)` |
| …einen Stub ohne DI trivial testen | `new MyStub(new Init('x'))` + `$stub->process()` |
| …prüfen, dass der Flow erfolgreich war | `$this->assertFlowOk()` |
| …prüfen, welche Stubs liefen | `assertStubExecuted()` / `assertStubNotExecuted()` |
| …den Return-Wert prüfen | `$r = $this->assertFlowReturned(Foo::class)` |
| …einen Fehlerpfad prüfen | `try { runFlow() } catch {}` + `assertFlowFailed()` + `assertFlowExceptionFrom()` |
| …nur einen Teil des Flows ausführen | `includeStubs: [...]` in `runFlow()` |
| …Services im Stub fälschen | `dependencies: [new FakeService()]` |
