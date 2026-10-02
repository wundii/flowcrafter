# REST-API
- [Back to README.md](./../README.md)

Die REST-API liefert ausschließlich JSON (kein Frontend — dafür gibt es
die separate [Web-UI](../README.md#web-ui)). Sie läuft über den
Flower-Micro-Router: im Dev-Modus über `service/index.php` (PHP
Built-in-Server), in Produktion über `service/worker.php` (FrankenPHP).

## Authentifizierung & Allgemeines

- Ist ein `serverSecret` gesetzt, erfordern alle Endpunkte außer `GET /`
  und `GET /metrics` den Header `Authorization: Bearer <secret>`.
  **Ohne Secret ist die gesamte API öffentlich.**
- CORS ist fest auf `Access-Control-Allow-Origin: *` gesetzt;
  `OPTIONS`-Requests werden immer mit `200` beantwortet.
- Datumsparameter (`from`, `to`) erwarten **RFC 3339 mit Millisekunden**,
  z. B. `2026-10-01T00:00:00.000+00:00`. Andere Formate werden
  stillschweigend ignoriert (kein Filter).
- Unbehandelte Fehler liefern `500` mit `error`, `file`, `line`, `trace`
  und `fileContext` (Quellcode-Ausschnitt) — siehe
  [Sicherheits-Checkliste](deployment.md#sicherheits-checkliste).

## Health & Info

| Methode | Pfad        | Auth  | Beschreibung                                                                                     |
|---------|-------------|-------|--------------------------------------------------------------------------------------------------|
| GET     | `/`         | keine | Status-Check                                                                                     |
| GET     | `/metrics`  | keine | Prometheus / OpenMetrics, siehe [monitoring.md](monitoring.md)                                   |
| GET     | `/api/ping` | ja    | Verbindungstest (`pong`)                                                                         |
| GET     | `/api/info` | ja    | `version`, `php`, `storage`, `queue`, `description`, `dev`, Heartbeats (`workers` = Observer, `scheduler`, `projection`) |

## Flows

| Methode | Pfad                             | Parameter                                              | Beschreibung                                                                                           |
|---------|----------------------------------|--------------------------------------------------------|--------------------------------------------------------------------------------------------------------|
| GET     | `/api/flow/flow-list`            | `sort`, `top`, `skip`, `type`, `status`, `from`, `to`  | Flow-Instanzen aus dem Index (paginiert)                                                               |
| GET     | `/api/flow/flow-details`         | `hash` **oder** `runtimeHash`                          | Kompletter Flow inkl. Messages, Exceptions, Results, Retries, Runs mit Step-Timings, Status, Read-Only-Gründe |
| GET     | `/api/flow/flow-search`          | `subject`, `top` (1–100, Default 10)                   | Flows nach `flowSubject` suchen                                                                        |
| GET     | `/api/flow/flow-stats`           | `from`, `to`, `type`                                   | Tägliche Flow-Statistiken                                                                              |
| GET     | `/api/flow/flow-type-stats`      | `from`, `to`                                           | Pro Flow-Typ: `prefix`, `flowType`, `total`, `failed`, `successRate`, `lastTime`, `group`              |
| GET     | `/api/flow/flow-projection-list` | —                                                      | Projection-Handler je Flow-Typ: `{ projectionHandlerClass, projectionMessageMethods }`                  |
| POST    | `/api/flow/flow-run`             | Body siehe unten                                       | Flow **synchron** ausführen (neu oder Re-Run)                                                          |

**`status`-Filter:** Ein oder mehrere Status-Namen, kommagetrennt
(`FAILED,WARNING`). `IN_PROGRESS` schließt `IN_PROGRESS_EXCEEDED` ein.

**`flow-details`** liefert auch ephemere Flows aus dem Index; diese sind
immer `isExecutable: false`.

### POST /api/flow/flow-run

```json
{
  "flowSource": "App\\Flow\\OrderFlow",
  "flowHash": "",
  "flowSubject": "order-42",
  "messageSource": "App\\Message\\OrderInit",
  "message": { "sku": "sku-42" },
  "includeSteps": []
}
```

| Feld            | Pflicht                         | Beschreibung                                                                       |
|-----------------|---------------------------------|------------------------------------------------------------------------------------|
| `flowSource`    | ja, wenn kein `flowHash`        | Flow-Klasse für einen **neuen** Flow                                               |
| `flowHash`      | ja, wenn kein `flowSource`      | Re-Run einer bestehenden Instanz (Typ, Source und Subject kommen aus dem Storage)   |
| `flowSubject`   | nein                            | Subject für neue Flows                                                             |
| `messageSource` | ja                              | Message-Klasse; muss von einem Step des Flows konsumiert werden                    |
| `message`       | ja (außer `EmptyInitMessage`)   | Message-Daten; Keys müssen den Constructor-Properties entsprechen                  |
| `includeSteps`  | nein                            | Siehe [concepts.md](concepts.md#selektive-step-ausführung-includesteps)            |

Antwort: `{ success: true, runtimeHash, messageReturn }`. Wirft ein Step,
wird die Exception im Flow gespeichert und die Antwort lautet trotzdem
`success: true` mit `messageReturn: null` — das Ergebnis über
`flow-details?runtimeHash=…` prüfen. Validierungsfehler liefern `400`,
ein unbekannter `flowHash` `404`.

## Exceptions

| Methode | Pfad                         | Parameter                                                | Beschreibung                                                     |
|---------|------------------------------|----------------------------------------------------------|------------------------------------------------------------------|
| GET     | `/api/flow/exception-list`   | `sort`, `top`, `skip`, `from`, `to`, `status`, `flowHash`| Flow-, Schedule-, Observer- und Projection-Exceptions (Feld `type`) |
| GET     | `/api/flow/exceptions-stats` | `from`, `to`                                             | Täglich: `{ date, flow, schedule, observer, projection }`        |

Mit `flowHash` werden nur die Exceptions dieses Flows geliefert. `status`
filtert nach dem Status des zugehörigen Flows.

## Schemas & Quellcode

| Methode | Pfad                                  | Parameter                     | Beschreibung                                                                |
|---------|---------------------------------------|-------------------------------|-----------------------------------------------------------------------------|
| GET     | `/api/flow/schema-list`               | —                             | Alle gespeicherten Flow-Schemas                                             |
| GET     | `/api/flow/schema-import-hashes`      | —                             | `{ schemaHash, messageSourceHash }` — Prüfsummen zur Änderungserkennung     |
| GET     | `/api/flow/step-source`               | `className` **oder** `stepHash` | Step-Quellcode (aktuell bzw. historischer Snapshot, mit `current`)        |
| GET     | `/api/flow/step-source-list`          | `stepSource`                  | Alle historischen Snapshots eines Steps                                     |
| GET     | `/api/flow/message-source-list`       | —                             | Alle Message-Source-Einträge (Property-Struktur + Hash)                     |
| GET     | `/api/flow/projection-handler-source` | `className`                   | Quellcode eines Projection-Handlers + gebundene `messageSources`            |

## Schedules

| Methode | Pfad                            | Parameter / Body | Beschreibung                                                          |
|---------|---------------------------------|------------------|-----------------------------------------------------------------------|
| GET     | `/api/schedule/schedule-list`   | —                | Alle entdeckten Schedules (Name, Cron, Klasse, `group`, `active`)     |
| GET     | `/api/schedule/schedule-source` | `className`      | Quellcode einer Schedule-Klasse                                       |
| POST    | `/api/schedule/flow-run`        | `{ className }`  | Schedule sofort ausführen (unabhängig von Cron und `active`)          |

Wirft der manuell gestartete Schedule, wird eine `ScheduleException`
gespeichert und `500` geliefert.

## Queue

| Methode | Pfad                     | Parameter / Body | Beschreibung                       |
|---------|--------------------------|------------------|------------------------------------|
| GET     | `/api/queue/queue-list`  | `sort`           | Offene Observer-Queue-Einträge     |
| GET     | `/api/queue/queue-count` | —                | Anzahl offener Einträge            |
| POST    | `/api/queue/enqueue`     | Body siehe unten | Message für den Observer einreihen |

### POST /api/queue/enqueue

```json
{
  "type": "flow.order.v1",
  "flowSource": "App\\Flow\\OrderFlow",
  "flowHash": null,
  "flowSubject": "order-42",
  "messageSource": "App\\Message\\OrderInit",
  "message": { "sku": "sku-42" },
  "includeSteps": []
}
```

Mit `flowHash` (Re-Run) werden `type` und `flowSource` aus dem Storage
übernommen, sonst sind beide Pflicht. Die Message wird vor dem Einreihen
probeweise hydriert, damit fehlerhafte Payloads sofort mit `400`
abgelehnt werden. Antwort: `{ queued: true, subject }`.

## Dev-Endpunkte

Nur aktiv, wenn `FLOWCRAFTER_DEV=1` gesetzt ist (automatisch bei
`vendor/bin/flowcrafter dev`). **Nicht in Produktion aktivieren.**

| Methode | Pfad                   | Parameter / Body                        | Beschreibung                                                                                                   |
|---------|------------------------|-----------------------------------------|----------------------------------------------------------------------------------------------------------------|
| GET     | `/api/dev/flow-list`   | —                                       | Alle Flow-Klassen im Projekt mit Typ, Gruppe und Dateipfad                                                     |
| GET     | `/api/dev/flow-source` | `className`                             | Schema, Hash-Vergleich (live vs. gespeichert), geänderte Message-Properties, Typschemata und Defaults der Init-Message |
| POST    | `/api/dev/flow-run`    | `{ className, messageSource, message }` | Flow **ohne Storage** ausführen; Projektionen laufen über eine In-Memory-Queue mit. Liefert Ausgaben, Fehler, Speicherverbrauch und das Flow-JSON |

Achtung: `flow-run` speichert nichts, die Steps selbst führen aber ihre
echten Seiteneffekte aus.

## Pagination

`/api/flow/flow-list` und `/api/flow/exception-list`:

| Parameter | Default | Beschreibung                    |
|-----------|---------|---------------------------------|
| `top`     | `1000`  | Maximale Einträge (1–10.000)    |
| `skip`    | `0`     | Offset                          |
| `sort`    | `desc`  | Sortierung (`asc` / `desc`)     |
| `from`    | —       | Startdatum (RFC 3339 mit ms)    |
| `to`      | —       | Enddatum (RFC 3339 mit ms)      |

Antwortformat: `{ items, total, hasMore }`
