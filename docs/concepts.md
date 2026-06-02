# Konzepte
- [Back to README.md](./../README.md)

## Flow

Ein Flow ist eine Workflow-Instanz, identifiziert durch einen `flowHash`
(UUIDv7) und einen `flowRuntimeHash` (UUIDv7 je Ausführung). Pro Flow
werden alle Messages, Exceptions, Runs und Results persistiert. Ein
optionales `flowSubject` erlaubt die Beschriftung von Flow-Instanzen.
Jeder Flow besitzt einen `flowType` (z. B. `flow.example.v1`) und einen
`lastTerm`-Zeitstempel des letzten Runs.

## Flow-Status

Jeder Flow trägt einen berechneten Status, der beim Lesen aus der SQLite
(`flow_list`) gespeichert und bei jedem `saveFlow()` aktualisiert wird:

| Status                  | Wert | Bedingung                                                                      |
| ----------------------- | ---- | ------------------------------------------------------------------------------ |
| `IN_PROGRESS`           | 0    | Runs vorhanden, aber noch nicht alle Leaf-Steps im letzten Run erreicht        |
| `IN_PROGRESS_EXCEEDED`  | 1    | `IN_PROGRESS` + letzter Run liegt > 1 Stunde zurück                            |
| `OK`                    | 2    | Alle relevanten Leaf-Steps erreicht, keine Exceptions, keine `false`-Results   |
| `WARNING`               | 3    | Alle Leaf-Steps erreicht, aber mindestens ein `FlowResult` mit `result = false` |
| `FAILED`                | 4    | Mindestens eine `FlowException` im letzten Run                                 |

**Leaf-Steps** sind Steps, deren Rückgabetypen von keinem weiteren Step
konsumiert werden (Endknoten des Workflow-Graphen). Erst wenn alle
relevanten Leaf-Steps im letzten Run Messages erhalten haben, gilt der
Flow als abgeschlossen.

Bei partiellen Re-Runs mit `includeSteps` werden nur diejenigen
Leaf-Steps geprüft, die im letzten Run tatsächlich Messages empfangen
haben — Leaf-Steps aus anderen Zweigen (z. B. bei AND-Joins, deren
zweite Eingabe fehlt) werden ignoriert.

## FlowSchema

Das Schema definiert den Workflow-Aufbau: welche
`StepInterface`-Implementierungen existieren, welche Nachrichtentypen sie
konsumieren und welcher Message-Typ den Flow initialisiert bzw.
abschließt. Das Schema wird per MD5 gehasht — stimmt der aktuelle Hash
nicht mit dem gespeicherten `flowSchemaHash` überein, gilt der Flow als
nicht ausführbar (`isExecutable = false`).

## Messages & State Transitions

| Zustand   | Bedeutung                                     |
| --------- | --------------------------------------------- |
| `WAIT`    | Message wartet auf weitere Inputs im Step     |
| `PROCESS` | Alle Inputs vorhanden, Step wird ausgeführt   |
| `FINISH`  | Message wurde verarbeitet                     |

Ein Step kann zurückgeben:
- `MessageInterface` → Flow läuft weiter
- `MessageReturnInterface` → Flow endet
- `bool` → Wird als `FlowResult` persistiert (pro Step-Ausführung mit
  `flowHash`, `flowRuntimeHash`, `stepSource`, `stepHash`, `result`, `time`)

## Selektive Step-Ausführung (`includeSteps`)

Wenn eine Message-Klasse von mehreren Steps konsumiert wird, können beim
Auslösen eines Runs gezielt einzelne Steps ausgewählt werden. Der
optionale Parameter `includeSteps` (Array von Step-Klassennamen) steuert,
welche Steps ausgeführt werden:

- **Leeres Array** (Default): Alle Steps werden wie gewohnt ausgeführt,
  alle Leaf-Steps müssen erreicht werden
- **Nicht-leeres Array**: Nur die aufgeführten Steps werden ausgeführt.
  Die Status-Berechnung berücksichtigt nur Leaf-Steps, die im letzten Run
  tatsächlich Messages empfangen haben — alle anderen werden ignoriert

Dies gilt sowohl für synchrone Ausführung (`/api/flow/flow-run`) als auch
für die Queue (`/api/queue/enqueue`).

## Observer (asynchrone Verarbeitung)

`appendObserveItem()` legt eine Message in die Queue. Der optionale
Parameter `flowSubject` wird dabei durchgereicht und beim Erstellen des
Flows als Beschriftung gesetzt. Der `FlowObserver`-Daemon pollt
`observeQueue()`, deserialisiert die Messages und führt sie via
`FlowRunner` aus. Exceptions werden protokolliert, der Observer läuft
mit 2s Retry-Delay weiter.

## Scheduler (zeitgesteuerte Ausführung)

Der `FlowScheduler` ermöglicht zeitgesteuerte Flow-Auslösung über
Cron-Ausdrücke. Schedule-Klassen werden automatisch aus dem
Composer-Classmap entdeckt — sie müssen `AbstractSchedule` erweitern
und das `#[FlowSchedule]`-Attribut tragen:

```php
use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;

#[FlowSchedule('0 */6 * * *', name: 'order-cleanup')]
class OrderCleanupSchedule extends AbstractSchedule
{
    public function process(): void
    {
        $this->enqueue(OrderFlow::class, new OrderInit('cleanup'));
    }
}
```

Innerhalb von `process()` stehen zwei Methoden bereit:
- `$this->enqueue(...)` — legt eine Message in die Queue (asynchron,
  vom Observer abgearbeitet)
- `$this->run(...)` — führt einen Flow synchron aus

Schedule-Klassen unterstützen Constructor-Injection mit den gleichen
`dependenciesInjection`-Einträgen wie Steps.

Der Scheduler trackt pro Schedule die letzte Ausführungsminute und
verhindert so Doppelausführungen innerhalb derselben Minute (relevant
im Dev-Modus, wo `tick()` häufiger aufgerufen wird).

## Projektion (Read Models)

Projection-Handler verarbeiten Messages eines Flows **asynchron** und
entkoppelt — typischerweise um Read Models aufzubauen, Benachrichtigungen
zu versenden oder Side-Effects auszulösen.

```php
use Wundii\Flowcrafter\Attribute\FlowProjection;
use Wundii\Flowcrafter\Attribute\FlowProjectionMessage;
use Wundii\Flowcrafter\FlowMessageReadonly;
use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;

#[FlowProjection(['flow.order.v1'])]
class OrderProjection implements ProjectionHandlerInterface
{
    #[FlowProjectionMessage(OrderValidated::class)]
    public function onValidated(FlowMessageReadonly $message): void { /* ... */ }
}
```

**Attribute:**
- `#[FlowProjection([flowTypes])]` (Klasse) — ordnet den Handler einem oder
  mehreren Flow-Typen zu. Pro Flow-Typ ist genau **ein** Handler zulässig.
- `#[FlowProjectionMessage(MessageSource::class)]` (Methode, wiederholbar) —
  bindet die Methode an einen Message-Source. Jede annotierte Methode muss
  einen `FlowMessageReadonly`-Parameter deklarieren (wird bei der Discovery
  validiert; doppelte Message-Sources innerhalb eines Handlers werfen).

**Ablauf:**
- Während eines Runs schreibt der `FlowRunner` jede finalisierte
  `FlowMessage` **inkrementell** in eine gemeinsame Projection-Queue —
  aber nur, wenn überhaupt ein Handler den Flow-Typ abonniert. Ein Run, der
  später eine Exception wirft, hat das bis dahin Abgeschlossene damit bereits
  projiziert.
- Der `ProjectionWorker` arbeitet die **eine** Queue message-zentriert ab:
  pro Message ermittelt er den Handler des Flow-Typs und ruft die zum
  `messageSource` registrierte Methode mit einer `FlowMessageReadonly` auf.
  Messages ohne passenden Handler/Methode werden übersprungen (acked).

**Fehlerverhalten (at-least-once):** Handler-Methoden müssen idempotent
sein. Wirft eine Methode, wird die Exception als `ProjectionException`
persistiert, die Message dennoch acked und mit der nächsten weitergemacht —
ein defekter Handler blockiert die gemeinsame Queue nicht.

Handler werden — wie Schedules — automatisch aus dem Composer-Classmap
entdeckt; keine manuelle Registrierung nötig. Der Worker läuft als
eigenständiger Prozess (`vendor/bin/flowcrafter projection:worker`) oder im
Dev-Modus als überwachter Subprozess mit.

## Step Retry

Steps können bei transienten Fehlern automatisch wiederholt werden.
Die Konfiguration erfolgt über `addStep()` im `FlowBuilder`:

```php
$builder->addStep(ExternalApiStep::class, retries: 3, delay: 500);
```

| Parameter | Default | Beschreibung |
|---|---|---|
| `retries` | `0` | Zusätzliche Versuche nach dem Erstversuch |
| `delay` | `200` | Fixer Delay in ms zwischen den Versuchen |

**Verhalten:**
- Bei einer Exception im `process()`-Aufruf wartet der Runner den
  konfigurierten Delay ab und versucht es erneut
- Bei jedem Versuch wird eine neue Step-Instanz erzeugt (frisches
  Autowiring)
- Sind alle Versuche erschöpft, wird die letzte Exception wie gewohnt
  als `FlowException` persistiert und erneut geworfen
- Output-Buffering wird bei fehlgeschlagenen Versuchen korrekt
  aufgeräumt

**Schema-Hash:**
`retries` und `delay` fließen in den Schema-Hash ein. Eine Änderung
der Retry-Config erzeugt einen neuen Schema-Eintrag im Storage.

**Storage-Kompatibilität:**
Die Felder werden in `Step::jsonSerialize()` persistiert. Beim
Deserialisieren älterer Schemas ohne diese Felder greift der
Fallback-Default (0/200) — keine Migration nötig.

## Step-Source-Snapshotting

Bei jeder Flow-Ausführung wird der Quellcode der beteiligten Steps als
`StepSourceEntity` gespeichert. Über die API kann der historische
Snapshot mit dem aktuellen Dateiinhalt verglichen werden
(`current: true/false`).
