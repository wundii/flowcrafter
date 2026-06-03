# REST-API
- [Back to README.md](./../README.md)

Die REST-API wird über `service/index.php` bereitgestellt (Flower
Micro-Router). Alle Endpunkte außer `GET /` und `GET /metrics` erfordern
einen Bearer-Token, sofern ein `serverSecret` konfiguriert ist.

## Flows & Exceptions

| Methode | Pfad                          | Parameter                                     | Beschreibung                                                                                                                                                                          |
|---------|-------------------------------|-----------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| GET     | `/api/ping`                   | —                                             | Verbindungstest (`pong`)                                                                                                                                                              |
| GET     | `/api/info`                   | —                                             | Version, Storage, Queue + Heartbeat-Status von Observer, Scheduler & Projection-Worker                                                                                                |
| GET     | `/api/flow/flow-list`         | `sort`, `top`, `skip`, `type`, `from`, `to`   | Flow-Instanzen (paginiert, filterbar)                                                                                                                                                 |
| GET     | `/api/flow/flow-details`      | `hash` oder `runtimeHash`                     | Flow mit Messages, Exceptions & Runs                                                                                                                                                  |
| GET     | `/api/flow/flow-stats`        | `from`, `to`, `type`                          | Tägliche Flow-Statistiken                                                                                                                                                             |
| GET     | `/api/flow/flow-search`       | `subject`, `top`                              | Flows nach `flowSubject` suchen                                                                                                                                                       |
| GET     | `/api/flow/flow-type-stats`   | `from`, `to`                                  | Flow-Typen mit Runs/Fehler/OK-Rate, optionalem `group` sowie `projectionHandlerClass` und `projectionMessageMethods` (Message-Source → Methode) des registrierten Projection-Handlers |
| GET     | `/api/flow/exception-list`    | `sort`, `top`, `skip`, `from`, `to`, `status` | Flow-, Schedule-, Observer- und Projection-Exceptions (paginiert, filterbar, klassifiziert via `type`)                                                                                |
| GET     | `/api/flow/exceptions-stats`  | `from`, `to`                                  | Tägliche Exception-Statistiken (`{ date, flow, schedule }`)                                                                                                                           |

## Schemas & Step-Source

| Methode | Pfad                                  | Parameter                   | Beschreibung                                                                |
|---------|---------------------------------------|-----------------------------|-----------------------------------------------------------------------------|
| GET     | `/api/flow/schema-list`               | —                           | Alle registrierten Flow-Schemas                                             |
| GET     | `/api/flow/step-source`               | `className` oder `stepHash` | Step-Quellcode (aktuell oder historisch)                                    |
| GET     | `/api/flow/step-source-list`          | `stepSource`                | Alle historischen Snapshots eines Steps                                     |
| GET     | `/api/flow/message-source-list`       | —                           | Alle Message-Source-Einträge                                                |
| GET     | `/api/flow/projection-handler-source` | `className`                 | Quellcode eines Projection-Handlers + Liste der gebundenen `messageSources` |

## Schedules

| Methode | Pfad                            | Parameter       | Beschreibung                                                       |
|---------|---------------------------------|-----------------|--------------------------------------------------------------------|
| GET     | `/api/schedule/schedule-list`   | —               | Alle entdeckten Schedules (Name, Cron, Klasse, optionales `group`) |
| GET     | `/api/schedule/schedule-source` | `className`     | Quellcode einer Schedule-Klasse                                    |
| POST    | `/api/schedule/flow-run`        | `{ className }` | Schedule manuell ausführen                                         |

## Ausführung & Queue

| Methode | Pfad                     | Body / Parameter                                                                         | Beschreibung                    |
|---------|--------------------------|------------------------------------------------------------------------------------------|---------------------------------|
| POST    | `/api/flow/flow-run`     | `{ flowHash, messageSource, message, includeSteps? }`                                    | Flow synchron ausführen         |
| POST    | `/api/queue/enqueue`     | `{ flowHash?, messageSource, message, includeSteps?, type?, flowSource?, flowSubject? }` | Message in die Queue stellen    |
| GET     | `/api/queue/queue-list`  | `sort`                                                                                   | Alle Queue-Einträge mit Details |
| GET     | `/api/queue/queue-count` | —                                                                                        | Aktuelle Queue-Größe            |

## Dev-Endpunkte

Nur verfügbar wenn der Server via `vendor/bin/flowcrafter dev` gestartet wurde (Umgebungsvariable `FLOWCRAFTER_DEV=1`). Nicht für den Produktionseinsatz vorgesehen.

| Methode | Pfad                    | Parameter                               | Beschreibung                                                                                           |
|---------|-------------------------|-----------------------------------------|--------------------------------------------------------------------------------------------------------|
| GET     | `/api/dev/flow-list`    | —                                       | Alle entdeckten Flow-Klassen mit Typ, Gruppe und Dateipfad                                             |
| GET     | `/api/dev/flow-source`  | `className`                             | Schema-Details eines Flows inkl. Hash-Vergleich (live vs. gespeichert) und Message-Property-Änderungen |
| POST    | `/api/dev/flow-run`     | `{ className, messageSource, message }` | Flow direkt (ohne Storage) synchron ausführen                                                          |

## Monitoring

| Methode | Pfad       | Auth  | Beschreibung                        |
|---------|------------|-------|-------------------------------------|
| GET     | `/metrics` | keine | Prometheus / OpenMetrics Exposition |

Details siehe [monitoring.md](monitoring.md).

## Pagination

Die Endpunkte `/api/flow/flow-list` und `/api/flow/exception-list` unterstützen Paginierung:

| Parameter | Default | Beschreibung                 |
|-----------|---------|------------------------------|
| `top`     | `1000`  | Maximale Einträge (1–10.000) |
| `skip`    | `0`     | Offset für Paginierung       |
| `sort`    | `desc`  | Sortierung (`asc` / `desc`)  |
| `from`    | —       | Startdatum (RFC 3339)        |
| `to`      | —       | Enddatum (RFC 3339)          |

Antwortformat: `{ items, total, hasMore }`
