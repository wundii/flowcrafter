# REST-API
- [Back to README.md](./../README.md)

Die REST-API wird über `service/index.php` bereitgestellt (Flower
Micro-Router). Alle Endpunkte außer `GET /` und `GET /metrics` erfordern
einen Bearer-Token, sofern ein `serverSecret` konfiguriert ist.

## Flows & Exceptions

| Methode | Pfad                  | Parameter                                        | Beschreibung                          |
| ------- | --------------------- | ------------------------------------------------ | ------------------------------------- |
| GET     | `/api/ping`           | —                                                | Verbindungstest (`pong`)              |
| GET     | `/api/info`           | —                                                | Server-Beschreibung + Observer-Status |
| GET     | `/api/flows`          | `sort`, `top`, `skip`, `type`, `from`, `to`      | Flow-Instanzen (paginiert, filterbar) |
| GET     | `/api/flows/detail`   | `hash` oder `runtimeHash`                        | Flow mit Messages, Exceptions & Runs  |
| GET     | `/api/flows/stats`    | `from`, `to`, `type`                             | Tägliche Flow-Statistiken             |
| GET     | `/api/flows/search`   | `subject`, `top`                                 | Flows nach `flowSubject` suchen       |
| GET     | `/api/exceptions`     | `sort`, `top`, `skip`, `flowHash`, `from`, `to`  | Exceptions (paginiert, filterbar)     |

## Schemas & Stub-Source

| Methode | Pfad                       | Parameter                   | Beschreibung                             |
| ------- | -------------------------- | --------------------------- | ---------------------------------------- |
| GET     | `/api/schemas`             | —                           | Alle registrierten Flow-Schemas          |
| GET     | `/api/schema/stub-source`  | `className` oder `stubHash` | Stub-Quellcode (aktuell oder historisch) |
| GET     | `/api/schema/stub-sources` | `stubSource`                | Alle historischen Snapshots eines Stubs  |

## Schedules

| Methode | Pfad                    | Parameter   | Beschreibung                                     |
| ------- | ----------------------- | ----------- | ------------------------------------------------ |
| GET     | `/api/schedules`        | —           | Alle entdeckten Schedules (Name, Cron, Klasse)   |
| GET     | `/api/schedule/source`  | `className` | Quellcode einer Schedule-Klasse                  |

## Ausführung & Queue

| Methode | Pfad               | Body / Parameter                                                                         | Beschreibung                    |
| ------- | ------------------ | ---------------------------------------------------------------------------------------- | ------------------------------- |
| POST    | `/api/flows/run`   | `{ flowHash, messageSource, message, includeStubs? }`                                    | Flow synchron ausführen         |
| POST    | `/api/queue`       | `{ flowHash?, messageSource, message, includeStubs?, type?, flowSource?, flowSubject? }` | Message in die Queue stellen    |
| GET     | `/api/queues`      | `sort`                                                                                   | Alle Queue-Einträge mit Details |
| GET     | `/api/queue/count` | —                                                                                        | Aktuelle Queue-Größe            |

## Monitoring

| Methode | Pfad       | Auth  | Beschreibung                        |
| ------- | ---------- | ----- | ----------------------------------- |
| GET     | `/metrics` | keine | Prometheus / OpenMetrics Exposition |

Details siehe [monitoring.md](monitoring.md).

## Pagination

Die Endpunkte `/api/flows` und `/api/exceptions` unterstützen Paginierung:

| Parameter | Default | Beschreibung                 |
| --------- | ------- | ---------------------------- |
| `top`     | `1000`  | Maximale Einträge (1–10.000) |
| `skip`    | `0`     | Offset für Paginierung       |
| `sort`    | `desc`  | Sortierung (`asc` / `desc`)  |
| `from`    | —       | Startdatum (RFC 3339)        |
| `to`      | —       | Enddatum (RFC 3339)          |

Antwortformat: `{ items, total, hasMore }`
