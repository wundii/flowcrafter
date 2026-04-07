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
| `IN_PROGRESS`           | 0    | Runs vorhanden, aber noch nicht alle Leaf-Stubs im letzten Run erreicht        |
| `IN_PROGRESS_EXCEEDED`  | 1    | `IN_PROGRESS` + letzter Run liegt > 1 Stunde zurück                            |
| `OK`                    | 2    | Alle relevanten Leaf-Stubs erreicht, keine Exceptions, keine `false`-Results   |
| `WARNING`               | 3    | Alle Leaf-Stubs erreicht, aber mindestens ein `FlowResult` mit `result = false` |
| `FAILED`                | 4    | Mindestens eine `FlowException` im letzten Run                                 |

**Leaf-Stubs** sind Stubs, deren Rückgabetypen von keinem weiteren Stub
konsumiert werden (Endknoten des Workflow-Graphen). Erst wenn alle
relevanten Leaf-Stubs im letzten Run Messages erhalten haben, gilt der
Flow als abgeschlossen.

Bei partiellen Re-Runs mit `includeStubs` werden nur diejenigen
Leaf-Stubs geprüft, die im letzten Run tatsächlich Messages empfangen
haben — Leaf-Stubs aus anderen Zweigen (z. B. bei AND-Joins, deren
zweite Eingabe fehlt) werden ignoriert.

## FlowSchema

Das Schema definiert den Workflow-Aufbau: welche
`StubInterface`-Implementierungen existieren, welche Nachrichtentypen sie
konsumieren und welcher Message-Typ den Flow initialisiert bzw.
abschließt. Das Schema wird per MD5 gehasht — stimmt der aktuelle Hash
nicht mit dem gespeicherten `flowSchemaHash` überein, gilt der Flow als
nicht ausführbar (`isExecutable = false`).

## Messages & State Transitions

| Zustand   | Bedeutung                                     |
| --------- | --------------------------------------------- |
| `WAIT`    | Message wartet auf weitere Inputs im Stub     |
| `PROCESS` | Alle Inputs vorhanden, Stub wird ausgeführt   |
| `FINISH`  | Message wurde verarbeitet                     |

Ein Stub kann zurückgeben:
- `MessageInterface` → Flow läuft weiter
- `MessageReturnInterface` → Flow endet
- `bool` → Wird als `FlowResult` persistiert (pro Stub-Ausführung mit
  `flowHash`, `flowRuntimeHash`, `stubSource`, `stubHash`, `result`, `time`)

## Selektive Stub-Ausführung (`includeStubs`)

Wenn eine Message-Klasse von mehreren Stubs konsumiert wird, können beim
Auslösen eines Runs gezielt einzelne Stubs ausgewählt werden. Der
optionale Parameter `includeStubs` (Array von Stub-Klassennamen) steuert,
welche Stubs ausgeführt werden:

- **Leeres Array** (Default): Alle Stubs werden wie gewohnt ausgeführt,
  alle Leaf-Stubs müssen erreicht werden
- **Nicht-leeres Array**: Nur die aufgeführten Stubs werden ausgeführt.
  Die Status-Berechnung berücksichtigt nur Leaf-Stubs, die im letzten Run
  tatsächlich Messages empfangen haben — alle anderen werden ignoriert

Dies gilt sowohl für synchrone Ausführung (`/api/flows/run`) als auch
für die Queue (`/api/queue`).

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
`dependenciesInjection`-Einträgen wie Stubs.

Der Scheduler trackt pro Schedule die letzte Ausführungsminute und
verhindert so Doppelausführungen innerhalb derselben Minute (relevant
im Dev-Modus, wo `tick()` häufiger aufgerufen wird).

## Stub-Source-Snapshotting

Bei jeder Flow-Ausführung wird der Quellcode der beteiligten Stubs als
`StubSourceEntity` gespeichert. Über die API kann der historische
Snapshot mit dem aktuellen Dateiinhalt verglichen werden
(`current: true/false`).
