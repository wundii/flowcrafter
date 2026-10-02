# Deployment (Produktion)
- [Back to README.md](./../README.md)

In Produktion laufen API-Server, Observer, Scheduler und
Projection-Worker als **getrennte Prozesse** bzw. Container. `dev` ist
dafür nicht geeignet.

## Docker-Dateien generieren

```bash
vendor/bin/flowcrafter docker:init
```

Erzeugt im Projektstamm:

- **`Dockerfile`** auf Basis von `dunglas/frankenphp` mit den
  PHP-Extensions aus der `composer.json` (`install-php-extensions`) und
  `composer install --no-dev`.
- **`docker-compose.yml`** mit den Diensten `redis`, `service`,
  `observer`, `scheduler`, `projection-worker` und `ui`
  (`wundii/flowcrafter-ui`, Port 5173).

Alle Flowcrafter-Container teilen sich zwei Volumes:

| Volume         | Mount              | Zweck                                         |
|----------------|--------------------|-----------------------------------------------|
| `service-data` | `/app/data`        | SQLite-Datei des Service-Index                |
| `observer-tmp` | `/tmp/flowcrafter` | Heartbeat-Dateien für `/api/info`, `/metrics` |

Die Config wird über `FLOWCRAFTER_CONFIG=/app/flowcrafter.php` gefunden.
Die generierte `docker-compose.yml` ist ein Ausgangspunkt — Secrets,
Restart-Policies und Healthchecks sollten angepasst werden (siehe unten).

## Einzeln starten (ohne Docker)

```bash
vendor/bin/flowcrafter service [--host=0.0.0.0] [--port=8000] [--workers=4] [--num-threads=8]
vendor/bin/flowcrafter observer [--workers=1]
vendor/bin/flowcrafter scheduler
vendor/bin/flowcrafter projection:worker
```

Alle Prozesse brauchen dieselbe `flowcrafter.php`, Zugriff auf dieselbe
SQLite-Datei und dasselbe Heartbeat-Verzeichnis.

## Container-Übersicht

| Container             | Command                                       | Skalierung                                                                 |
|-----------------------|-----------------------------------------------|----------------------------------------------------------------------------|
| **service**           | `vendor/bin/flowcrafter service`              | vertikal (FrankenPHP-Worker/Threads)                                       |
| **observer**          | `vendor/bin/flowcrafter observer --workers N` | horizontal (alle Queue-Backends verteilen atomar)                          |
| **scheduler**         | `vendor/bin/flowcrafter scheduler`            | **genau eine** Instanz — mehrere führen Schedules doppelt aus              |
| **projection-worker** | `vendor/bin/flowcrafter projection:worker`    | horizontal bei Redis- und MySQL-Queue; bei ESDB-Queue **nur eine** Instanz |

## FrankenPHP-Worker

`service` erzeugt ein Caddyfile und startet `service/worker.php` im
Worker-Modus: Config, Storage-, Queue-Verbindungen und Routing werden
einmal pro Worker gebootet und für alle Requests wiederverwendet.

| Einstellung     | Bedeutung                                                                                 |
|-----------------|-------------------------------------------------------------------------------------------|
| `workers` (N)   | persistente, vorgewärmte PHP-Worker                                                        |
| `num-threads` (M) | gesamte Thread-Anzahl, Default `N × 2`, mindestens `N + 1`; Threads > N bearbeiten Überlauf |
| `max_threads`   | automatisch `M × 2`                                                                        |

Das Caddyfile setzt `admin off`, JSON-Logs auf stderr, Timeouts
(`read_header 5s`, `read_body 30s`, `write 60s`, `idle 120s`) und
Kompression (`zstd`, `gzip`). Ohne `setServerHttps(true)` ist
`auto_https off`.

> Weil Worker langlebig sind, bleibt Zustand in registrierten
> DI-Instanzen (`instance()`, `bind(…, object)`) über Requests hinweg
> erhalten. Code-Änderungen erfordern einen Neustart des Service.

## Zustellgarantien & Betrieb

| Thema                         | Verhalten                                                                                                   |
|-------------------------------|-------------------------------------------------------------------------------------------------------------|
| Observer-Queue                | **at-most-once** — ein Item wird beim Abholen entfernt; ein Absturz während der Ausführung verliert das Item |
| Projection-Queue              | **at-least-once** — erneute Zustellung nur bei Absturz vor dem Ack (300 s Sichtbarkeit, Redis/MySQL)         |
| Retries                       | nur auf Step-Ebene (`retries`/`delay`); kein Queue-Retry, keine Dead-Letter-Queue                            |
| Fehler im Projection-Handler  | als `ProjectionException` gespeichert, Message gilt als verarbeitet                                         |
| Ephemere Flows                | werden nur durch den Scheduler aufgeräumt — ohne Scheduler wächst `flow_ephemeral_list`                       |
| Redis-Projection-Stream       | wird nicht getrimmt; bei hohem Volumen periodisch per `XTRIM` begrenzen                                       |
| Service-Index                 | nach Restore oder Ausfall per `storage:rebuild` aus dem Primär-Storage neu aufbauen                          |

**Restart-Policies:** Observer und Scheduler fangen Fehler selbst ab und
machen nach 2 s weiter; der Projection-Worker beendet sich bei Fehlern
außerhalb eines Handlers (z. B. Verbindungsverlust). Alle Worker-Container mit
`restart: unless-stopped` (oder vergleichbar) betreiben.

**Healthchecks:** Die generierten Checks suchen irgendeine frische
`*.heartbeat`-Datei im gemeinsamen Volume — sie schlagen also nicht an,
solange *irgendein* Worker lebt. Für aussagekräftige Checks den Dateinamen
eingrenzen, z. B. für den Observer:

```yaml
test: ["CMD-SHELL", "find /tmp/flowcrafter -name 'observer.*.heartbeat' -mmin -1 -type f | grep -q ."]
```

Analog `scheduler.*` und `projection.*`. Alternativ `/metrics`
auswerten (siehe [monitoring.md](monitoring.md)).

## Sicherheits-Checkliste

| Punkt                     | Empfehlung                                                                                                    |
|---------------------------|---------------------------------------------------------------------------------------------------------------|
| API-Secret                | `setServerSecret()` **immer** setzen — ohne Secret ist die gesamte API inkl. Ausführungs-Endpunkten offen      |
| Dev-Modus                 | `FLOWCRAFTER_DEV` in Produktion **nicht** setzen — sonst führt `/api/dev/flow-run` beliebige Flows aus          |
| Netzwerk                  | Service nicht direkt ins Internet stellen; Reverse-Proxy/Firewall davor. CORS ist fest auf `*` gesetzt          |
| Fehlerantworten           | 500er enthalten Exception-Message, Datei, Zeile, Stacktrace und Quellcode-Ausschnitt — nur intern exponieren    |
| Quellcode-Endpunkte       | `/api/flow/step-source`, `/api/schedule/schedule-source`, `/api/flow/projection-handler-source` liefern PHP-Quellcode |
| `/` und `/metrics`        | immer ohne Auth erreichbar — auf Netzwerkebene absichern                                                       |
| Redis                     | Passwort setzen (`RedisStorageConfig`/`RedisQueueConfig` haben einen `password`-Parameter), Port nicht publizieren |
