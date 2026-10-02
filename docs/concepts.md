# Konzepte
- [Back to README.md](./../README.md)

## Inhalt

- [Bausteine: Messages, Steps, Flow](#bausteine-messages-steps-flow)
- [FlowBuilder-Validierung](#flowbuilder-validierung)
- [Flow-Instanz, Runs & Identität](#flow-instanz-runs--identität)
- [Ausführung im FlowRunner](#ausführung-im-flowrunner)
- [Messages & State Transitions](#messages--state-transitions)
- [Flow-Status](#flow-status)
- [Step Retry](#step-retry)
- [runOnce — Step-Ergebnis bei Re-Runs wiederverwenden](#runonce--step-ergebnis-bei-re-runs-wiederverwenden)
- [Selektive Step-Ausführung (includeSteps)](#selektive-step-ausführung-includesteps)
- [Ephemere Flows (#[FlowEphemeral])](#ephemere-flows-flowephemeral)
- [Versionierung, Schema-Hash & Read-Only](#versionierung-schema-hash--read-only)
- [Flows aus Steps heraus auslösen (AbstractStep)](#flows-aus-steps-heraus-auslösen-abstractstep)
- [Observer (asynchrone Verarbeitung)](#observer-asynchrone-verarbeitung)
- [Scheduler (zeitgesteuerte Ausführung)](#scheduler-zeitgesteuerte-ausführung)
- [Projektion (Read Models)](#projektion-read-models)
- [Discovery](#discovery)
- [Step-Source-Snapshotting](#step-source-snapshotting)

## Bausteine: Messages, Steps, Flow

**Messages** sind `readonly` Value-Objects, die von `AbstractMessage` erben.
Serialisiert werden ausschließlich die *promoted* Constructor-Properties.
Drei Marker-Interfaces bestimmen die Rolle:

| Interface                | Rolle                               | `MessageEnum`      |
|--------------------------|-------------------------------------|--------------------|
| `MessageInitInterface`   | Startet den Flow                    | `INIT` (`init`)    |
| `MessageDataInterface`   | Zwischenergebnis zwischen Steps     | `DATA` (`step`)    |
| `MessageReturnInterface` | Terminal — beendet einen Flow-Zweig | `RETURN` (`return`)|

Braucht der erste Step keinen Input, gibt es die mitgelieferte
`Wundii\Flowcrafter\EmptyInitMessage`. Sie muss als `public readonly`
Promoted-Property deklariert werden, damit Rector sie nicht als ungenutzten
Parameter entfernt.

**Steps** implementieren `StepInterface` (`process()` + `returnTypes()`).
Welche Messages ein Step konsumiert, wird per Reflection aus seinem
**Constructor** gelesen — jeder Parameter, dessen Typ `MessageInterface`
implementiert, ist ein Input. Alle anderen Constructor-Parameter sind
Services und werden per DI aufgelöst (siehe
[configuration.md](configuration.md#dependency-injection)). `returnTypes()`
wird auf einer Instanz *ohne* Constructor aufgerufen — die Methode darf also
nicht auf Properties zugreifen.

Ein Step kann zurückgeben:
- `MessageDataInterface` → Flow läuft weiter, alle Consumer der Message werden angestoßen
- `MessageReturnInterface` → Zweig endet, die Message wird zum Rückgabewert von `run()`
- `bool` → wird als `FlowResult` persistiert (Leaf-Ergebnis, `false` führt zu `WARNING`)

Der **Flow** ist eine Klasse, die `FlowInterface::schema()` implementiert und
ein `FlowSchema` per `FlowBuilder` liefert.

## FlowBuilder-Validierung

`new FlowBuilder($type, $initMessage, $returnMessage = null)` und
`build()` prüfen:

| Regel                                                                  | Zeitpunkt   |
|------------------------------------------------------------------------|-------------|
| Typ hat das Format `flow.<name>.v<N>`                                  | Constructor |
| Init-Message implementiert `MessageInitInterface`                      | Constructor |
| Return-Message (falls gesetzt) implementiert `MessageReturnInterface`  | Constructor |
| Step implementiert `StepInterface` und ist nicht doppelt registriert   | `addStep()` |
| Init-Message wird von mindestens einem Step konsumiert                 | `build()`   |
| Return-Message taucht in mindestens einem `returnTypes()` auf          | `build()`   |
| Keine Zyklen im Step-Graph (DFS)                                       | `build()`   |
| Alle Steps sind vom Init-Step aus erreichbar (BFS)                     | `build()`   |
| Jede erzeugte `MessageDataInterface` wird von einem Step konsumiert    | `build()`   |

> **Einschränkung:** Die Erreichbarkeitsprüfung startet beim **ersten**
> Step, der die Init-Message konsumiert. Konsumieren mehrere Steps direkt
> die Init-Message, werden die weiteren als „nicht verbunden" abgelehnt.
> Workaround: ein vorgeschalteter Step, der eine Data-Message erzeugt.

## Flow-Instanz, Runs & Identität

| Feld               | Bedeutung                                                                     |
|--------------------|-------------------------------------------------------------------------------|
| `flowHash`         | UUIDv7, identifiziert die Flow-**Instanz** (bleibt bei Re-Runs gleich)        |
| `flowRuntimeHash`  | UUIDv7, identifiziert einen einzelnen **Run** (Ausführung)                    |
| `flowType`         | z. B. `flow.order.v1` — muss mit dem Schema-Typ übereinstimmen                |
| `flowSource`       | Klassenname der `FlowInterface`-Implementierung                               |
| `flowSchemaHash`   | MD5 des Schemas zum Zeitpunkt der ersten Ausführung                           |
| `flowSubject`      | Optionaler Geschäfts-Key (z. B. Order-ID) für die Suche                       |

Ein Flow sammelt über alle Runs hinweg `FlowMessage[]`, `FlowException[]`,
`FlowResult[]`, `FlowRetry[]` und `FlowRun[]`. Ein Re-Run (`run(..., flowHash: ...)`)
übernimmt `flowSubject` und `flowSchemaHash` der gespeicherten Instanz und
hängt einen neuen Run an.

## Ausführung im FlowRunner

```php
$runner = new FlowRunner(
    type: 'flow.order.v1',
    flowSource: OrderFlow::class,
    flowSubject: 'order-42',          // optional
    storage: $storage,                // optional — ohne Storage rein in-memory
    queue: $queue,                    // optional — nötig für Projektionen und enqueue() aus Steps
    dependencyRegistry: $registry,    // optional
    projectionHandlerMetas: ProjectionDiscovery::discover(), // optional
);

$result = $runner->run(
    message: new OrderInit('sku-42'),
    flowHash: null,          // gesetzt = Re-Run einer bestehenden Instanz
    queueId: null,           // wird vom Observer gesetzt
    includeSteps: [],        // siehe unten
);                            // MessageReturnInterface|bool (false, wenn kein Return)
```

Ablauf:

1. Neue `Flow`-Instanz anlegen; bei Re-Runs Subject und Schema-Hash aus dem
   Storage übernehmen. Ist der Flow nicht ausführbar (Schema geändert,
   Read-Only), wirft `run()` eine `InvalidArgumentException`.
2. Schema, Instanz und Run im Storage registrieren (bei ephemeren Flows nur das Schema).
3. Rekursive, **tiefenorientierte** Verarbeitung: Für jede Message werden
   alle konsumierenden Steps in der Reihenfolge von `addStep()` angestoßen.
4. Ein Step läuft erst, wenn **alle** seine Input-Messages im Zustand `WAIT`
   vorliegen (**Fan-in / AND-Join**). Fehlt ein Input noch, wartet der Step,
   bis der andere Zweig ihn liefert.
5. Pro Versuch wird eine frische Step-Instanz über einen Symfony
   `ContainerBuilder` gebaut; Messages werden als synthetische Services
   injiziert. Ausgaben auf stdout werden per Output-Buffering abgefangen
   (`FlowRunner::getOutput()`).
6. Am Ende wird der Flow komplett gespeichert (`appendFlow()`), inklusive
   Aktualisierung des SQLite-Index.

**Rückgabewert:** Die *erste* `MessageReturnInterface` gewinnt. Gibt es
keine, ist es das zuletzt gelieferte `bool` bzw. `false`. Alle `FlowResult`s
und Return-Messages werden unabhängig davon vollständig aufgezeichnet.

**Fehler:** Sind alle Versuche eines Steps erschöpft, werden seine Inputs
auf `FINISH` gesetzt, eine `FlowException` persistiert, der Flow gespeichert
und die Exception **erneut geworfen**. Andere Zweige laufen danach nicht weiter.

## Messages & State Transitions

| Zustand   | Bedeutung                                   |
|-----------|---------------------------------------------|
| `WAIT`    | Message liegt für einen Step bereit         |
| `PROCESS` | Alle Inputs vorhanden, Step wird ausgeführt |
| `FINISH`  | Message wurde verarbeitet                   |

Jede `FlowMessage` gehört zu genau einem Step (`stepSource`). Wird eine
Message von drei Steps konsumiert, entstehen drei `FlowMessage`-Einträge.
Doppelte Zustandswechsel werfen eine Exception. `predecessorHash` verweist
auf die `FlowMessage`, deren Step die Message erzeugt hat.

## Flow-Status

`Flow::status()` wird aus dem **letzten Run** berechnet:

| Status                 | Wert  | Bedingung                                                                       |
|------------------------|-------|---------------------------------------------------------------------------------|
| `IN_PROGRESS`          | 0     | Noch keine Runs oder noch nicht alle relevanten Leaf-Steps erreicht             |
| `IN_PROGRESS_EXCEEDED` | 1     | `IN_PROGRESS` + letzter Run liegt > 1 Stunde zurück                             |
| `OK`                   | 2     | Alle relevanten Leaf-Steps erreicht, keine Exceptions, keine `false`-Results    |
| `WARNING`              | 3     | Alle Leaf-Steps erreicht, aber mindestens ein `FlowResult` mit `result = false` |
| `FAILED`               | 4     | Mindestens eine `FlowException` im letzten Run                                  |

**Leaf-Steps** sind Steps, deren Rückgabetypen von keinem anderen Step
konsumiert werden. Ein Leaf gilt als erreicht, wenn er im letzten **oder
einem früheren** Run Messages erhalten hat — so bleiben partielle Re-Runs
korrekt.

> **Hinweis:** Der Status in der Flow-Liste (SQLite-Index) ist eine
> Momentaufnahme vom Zeitpunkt des Speicherns. Ein Flow, der hängen bleibt,
> wechselt dort nicht automatisch auf `IN_PROGRESS_EXCEEDED`; die
> Detail-Ansicht (`/api/flow/flow-details`) berechnet den Status dagegen live.

## Step Retry

```php
$builder->addStep(ExternalApiStep::class, retries: 3, delay: 500);
```

| Parameter | Default | Beschreibung                              |
|-----------|---------|-------------------------------------------|
| `retries` | `0`     | Zusätzliche Versuche nach dem Erstversuch |
| `delay`   | `200`   | Fixer Delay in ms zwischen den Versuchen  |

- Jeder Versuch erzeugt eine **neue** Step-Instanz (frisches Autowiring).
- Jeder fehlgeschlagene Zwischenversuch wird als `FlowRetry` gespeichert
  (Tabelle `flow_step_retry` bzw. `flow:step:retry:*`).
- Der Delay ist **blockierend** (`usleep`) — der Worker-Prozess wartet.
- Sind alle Versuche erschöpft, folgt das normale Fehlerverhalten.
- `retries`/`delay` fließen in den Schema-Hash ein; ältere Schemas ohne
  diese Felder fallen auf die Defaults zurück.

## runOnce — Step-Ergebnis bei Re-Runs wiederverwenden

```php
$builder->addStep(ChargeCreditCardStep::class, runOnce: true);
```

Bei einem **Re-Run** einer bestehenden Instanz wird ein `runOnce`-Step
nicht erneut ausgeführt, wenn es aus einem früheren Run bereits ein
Ergebnis gibt — entweder eine erzeugte Message eines seiner `returnTypes`
oder ein `FlowResult` dieses Steps. Das gespeicherte Ergebnis wird
stattdessen weitergereicht. Typischer Einsatz: Seiteneffekte, die nicht
doppelt passieren dürfen (Zahlung, E-Mail, externe Buchung). Ohne Storage
(oder beim ersten Run) läuft der Step normal. `runOnce` fließt in den
Schema-Hash ein.

## Selektive Step-Ausführung (includeSteps)

`includeSteps` (Array von Step-Klassennamen) beschränkt einen Run auf
bestimmte Steps:

- **Leeres Array** (Default): alle Steps laufen.
- **Nicht-leer:** Die Liste wird automatisch um alle **nachgelagerten**
  Steps erweitert. Nicht enthaltene Steps werden übersprungen.
- **Historische Inputs:** Benötigt ein eingeschlossener Step eine Message,
  die in diesem Run nicht entsteht (z. B. der zweite Input eines Fan-in),
  übernimmt der Runner die zuletzt gespeicherte Message aus dem früheren
  Run der Instanz (nur mit Storage und `flowHash`).
- Die Status-Berechnung prüft nur Leaf-Steps, die im letzten Run
  tatsächlich Messages erhalten haben.

Verfügbar über `FlowRunner::run()`, `/api/flow/flow-run` und
`/api/queue/enqueue`.

## Ephemere Flows (#[FlowEphemeral])

```php
#[FlowEphemeral(expiryDays: 7)]   // Default: 14, Minimum: 1
class HealthCheckFlow implements FlowInterface { /* ... */ }
```

Für hochfrequente, kurzlebige Flows, deren Details nicht dauerhaft benötigt
werden:

- Es wird **nichts** in das primäre Backend geschrieben (keine Instanz,
  keine Messages, keine Runs, keine Exceptions) — nur das Schema.
- Der komplette Flow landet als JSON in der SQLite-Tabelle
  `flow_ephemeral_list` und ist über die API normal einsehbar.
- Abgelaufene Einträge entfernt der **Scheduler** bei jedem Tick
  (`cleanupEphemeral()`). Läuft kein Scheduler, wird nicht aufgeräumt.
- Ephemere Flows sind über die API nicht erneut ausführbar und gehen bei
  `storage:rebuild --clear` verloren.
- Projektionen funktionieren wie bei persistenten Flows.

## Versionierung, Schema-Hash & Read-Only

- **Schema-Hash:** MD5 über das serialisierte Schema (Typ, Steps mit
  Inputs, Return-Types, `retries`, `delay`, `runOnce` sowie die Hashes der
  beteiligten Messages). `#[FlowGroup]` beeinflusst den Hash nicht.
- **Ausführbarkeit:** Ein gespeicherter Flow ist nur ausführbar
  (`isExecutable()`), wenn sein `flowSchemaHash` dem aktuellen Code
  entspricht. Ändert sich der Flow-Aufbau, sollte daher der Typ hochgezählt
  werden (`flow.order.v2`).
- **Message-Hash** (`Source::message()`): MD5 über die sortierten Namen der
  Promoted-Properties (inkl. Kurzname verschachtelter Klassentypen,
  rekursiv für verschachtelte Messages). Typänderungen skalarer Properties
  ändern den Hash *nicht*, Umbenennungen schon.
- **Step-Hash** (`Source::step()`): MD5 über den kompletten Dateiinhalt.
- **Read-Only:** Beim Laden prüft der `Converter`, ob Flow-, Step- und
  Message-Klassen noch existieren, der Flow-Typ passt und die Message-Hashes
  übereinstimmen. Andernfalls wird der **gesamte** Flow read-only geladen,
  Messages werden zu `ReadonlyMessage` (Rohdaten), und `readOnlyReasons`
  erklärt warum. Read-only Flows bleiben lesbar, sind aber nicht ausführbar.

## Flows aus Steps heraus auslösen (AbstractStep)

Steps können optional von `AbstractStep` erben. Sie erhalten dann Zugriff
auf die Metadaten des laufenden Flows (`getFlowHash()`,
`getFlowRuntimeHash()`, `getFlowType()`, `getFlowSchemaHash()`,
`getFlowSubject()`) sowie zwei Helfer, um **andere** Flows anzustoßen.
Storage, Queue, DI-Registry und Projection-Handler werden dabei vom
laufenden Runner übernommen:

```php
class OrderPlacedStep extends AbstractStep
{
    public function process(): bool
    {
        $this->enqueue(InvoiceFlow::class, new InvoiceInit($this->getFlowSubject()));
        return true;
    }
    // ...
}
```

| Methode     | Verhalten                                                                  |
|-------------|----------------------------------------------------------------------------|
| `enqueue()` | Asynchron über die Queue (fire-and-forget) — **empfohlen**                 |
| `run()`     | Synchron über einen neuen `FlowRunner`, liefert dessen Rückgabewert        |

`run()` führt den Sub-Flow im selben Prozess aus und hat keinen
Zyklenschutz über Flow-Grenzen hinweg — rekursive Ketten können den Stack
sprengen. Ohne Queue (z. B. `FlowRunner` ohne `queue`) wirft `enqueue()`
eine `RuntimeException`.

## Observer (asynchrone Verarbeitung)

`QueueInterface::appendObserveItem()` legt eine Message samt `type`,
`flowSource`, optionalem `flowHash` (Re-Run), `includeSteps` und
`flowSubject` in die Queue. Der `FlowObserver` liest `observeQueue()`,
hydriert die Message per `wundii/data-mapper` (Constructor-Ansatz) und
führt sie über einen frischen `FlowRunner` aus. Die `queueId` wird am Run
gespeichert.

- **Zustellung: at-most-once.** Das Item wird beim Abholen aus der Queue
  entfernt. Stürzt der Prozess während der Ausführung ab, ist das Item weg;
  der bereits begonnene Flow bleibt im Storage als `IN_PROGRESS` sichtbar.
- Wirft ein Flow, wird die Exception als `FlowException` gespeichert. Tritt
  der Fehler *außerhalb* eines Steps auf (z. B. Message nicht hydrierbar,
  Schema geändert), wird stattdessen eine `ObserverException` gespeichert.
  Der Observer-Command startet die Verarbeitung nach 2 s Pause neu.
- Es gibt kein Queue-Level-Retry und keine Dead-Letter-Queue —
  Wiederholungen werden über `retries` am Step konfiguriert.

## Scheduler (zeitgesteuerte Ausführung)

Schedule-Klassen erweitern `AbstractSchedule` und tragen `#[FlowSchedule]`:

```php
use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;

#[FlowSchedule('0 */6 * * *', name: 'order-cleanup', group: 'Maintenance')]
class OrderCleanupSchedule extends AbstractSchedule
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function process(): void
    {
        $this->enqueue(OrderFlow::class, new OrderInit('cleanup'));
        // oder synchron: $this->run(OrderFlow::class, new OrderInit('cleanup'));
    }
}
```

| Attribut-Parameter | Default | Bedeutung                                         |
|--------------------|---------|---------------------------------------------------|
| `expression`       | —       | Cron-Ausdruck (`dragonmantank/cron-expression`)   |
| `name`             | `null`  | Anzeigename (Logs, UI); Fallback: Klassenname     |
| `group`            | `null`  | Gruppierung in der UI                             |
| `active`           | `true`  | `false` deaktiviert den Schedule im Scheduler     |

- Der Scheduler prüft einmal pro Minute alle Schedules (`tick()`) und
  schläft dann bis zur nächsten vollen Minute. Pro Schedule wird die
  letzte Ausführungsminute gemerkt, um Doppelausführungen zu vermeiden.
- Schedules werden pro Ausführung neu über den DI-Container gebaut
  (Constructor-Injection über die `DependencyRegistry`).
- Wirft `process()`, wird eine `ScheduleException` gespeichert; der
  Scheduler läuft weiter.
- Ein Tick, der länger als eine Minute dauert, lässt Fälligkeiten aus.
  Lang laufende Arbeit daher per `enqueue()` an den Observer abgeben.
- **Nur eine Instanz** betreiben — mehrere Scheduler führen jeden Schedule
  mehrfach aus.

## Projektion (Read Models)

Projection-Handler reagieren **asynchron** auf einzelne Messages eines
Flows — für Read Models, Benachrichtigungen oder Side-Effects.

```php
use Wundii\Flowcrafter\Attribute\FlowProjection;
use Wundii\Flowcrafter\Attribute\FlowProjectionMessage;
use Wundii\Flowcrafter\FlowMessageReadonly;
use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;

#[FlowProjection(['flow.order.v1'])]
class OrderProjection implements ProjectionHandlerInterface
{
    #[FlowProjectionMessage(OrderValidated::class)]
    public function onValidated(FlowMessageReadonly $message): void
    {
        $data = $message->getMessage()->getRawData(); // Rohdaten der Message
    }
}
```

**Regeln (bei der Discovery validiert):**
- Ein Handler deklariert mindestens einen Flow-Typ.
- Pro Flow-Typ ist genau **ein** Handler zulässig.
- Pro Handler darf eine Message-Klasse nur an **eine** Methode gebunden sein.
- Jede gebundene Methode akzeptiert ein `FlowMessageReadonly`.

**Ablauf:**
- Der `FlowRunner` stellt jede Message in die Projection-Queue, sobald sie
  den Zustand `FINISH` erreicht — inkrementell, d. h. auch ein später
  fehlschlagender Run projiziert, was bis dahin abgeschlossen war.
- Pro Run wird jede Message-Klasse nur **einmal** projiziert (auch wenn sie
  an mehrere Steps ging).
- Es wird nur projiziert, wenn ein Handler den Flow-Typ abonniert hat und
  der Runner eine Queue hat.
- Der `ProjectionWorker` übergibt die Message als `FlowMessageReadonly`
  (Rohdaten, keine hydrierte Message-Klasse). Messages ohne passenden
  Handler/Methode werden bestätigt und übersprungen.

**Fehlerverhalten (at-least-once):** Handler-Methoden müssen idempotent
sein. Wirft eine Methode, wird eine `ProjectionException` gespeichert, die
Message trotzdem bestätigt und mit der nächsten weitergemacht. Erneute
Zustellung passiert nur, wenn ein Worker abstürzt, bevor er bestätigt
(Sichtbarkeits-Timeout 300 s bei Redis und MySQL). Handler-Instanzen
werden pro Worker-Prozess einmal gebaut und wiederverwendet — sie sollten
also keinen Zustand zwischen Messages halten.

## Discovery

Flows (für die Dev-Endpunkte), Schedules und Projection-Handler werden
automatisch gefunden. `ClassResolver` liest dafür die Composer-Classmap
(`vendor/composer/autoload_classmap.php`) **und** durchsucht alle
PSR-4-Verzeichnisse außerhalb von `vendor/`. Dateien dieser Verzeichnisse
werden dabei per `require_once` geladen — sie sollten also keine
Seiteneffekte beim Laden haben. Ergebnisse werden pro Prozess gecacht;
neue Klassen werden erst nach einem Neustart erkannt (im `dev`-Modus
automatisch über den File-Watcher).

## Step-Source-Snapshotting

Bei jeder Ausführung wird der Quellcode der beteiligten Steps als
`StepSourceEntity` (MD5-Hash + Dateiinhalt) und die Property-Struktur der
Messages als `MessageSourceEntity` gespeichert. Über die API lässt sich ein
historischer Snapshot mit dem aktuellen Dateiinhalt vergleichen
(`/api/flow/step-source`, `current: true/false`).
