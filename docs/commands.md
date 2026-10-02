# Console Commands
- [Back to README.md](./../README.md)

Alle Commands akzeptieren `--config <pfad>` / `-c <pfad>`. Ohne Angabe
wird `flowcrafter.php` im aktuellen Arbeitsverzeichnis geladen.

## Übersicht

| Command               | Zweck                                                              | Läuft dauerhaft |
|-----------------------|--------------------------------------------------------------------|-----------------|
| `config:create`       | `flowcrafter.php` aus der Vorlage erzeugen                          | nein            |
| `storage:init`        | Storage, Queue und Service-Index initialisieren                    | nein            |
| `storage:rebuild`     | Service-Index (SQLite) aus dem primären Storage neu aufbauen       | nein            |
| `diagram:mermaid`     | Mermaid-Zustandsdiagramm für einen Flow erzeugen                   | nein            |
| `docker:init`         | `Dockerfile` und `docker-compose.yml` erzeugen                     | nein            |
| `dev`                 | Entwicklungsumgebung: API + Observer (+ Scheduler, Projection)     | ja              |
| `service`             | API-Server mit FrankenPHP im Worker-Modus (Produktion)             | ja              |
| `observer`            | Observer-Worker für die asynchrone Queue                           | ja              |
| `scheduler`           | Scheduler für `#[FlowSchedule]`-Klassen                            | ja              |
| `projection:worker`   | Projection-Worker für `#[FlowProjection]`-Handler                  | ja              |

## config:create

```bash
vendor/bin/flowcrafter config:create
```

Kopiert `templates/flowcrafter.php.dist` nach `./flowcrafter.php`
(fragt vorher nach).

## storage:init

```bash
vendor/bin/flowcrafter storage:init
```

Ruft `initializeDatabase()` des Storage (inkl. SQLite-Index) und
`initializeQueue()` der Queue auf.

## storage:rebuild

```bash
vendor/bin/flowcrafter storage:rebuild [--clear]
```

Liest alle Flow-Hashes aus dem primären Storage und schreibt jeden Flow
erneut in den SQLite-Index. Mit `--clear` werden vorher **alle**
Index-Tabellen geleert — dabei gehen ephemere Flows sowie Schedule-,
Observer- und Projection-Exceptions verloren, da sie nicht aus dem
Primär-Storage rekonstruiert werden.

## diagram:mermaid

```bash
vendor/bin/flowcrafter diagram:mermaid 'App\Flow\OrderFlow' [--output=./]
```

Schreibt `<flowType>.mmd` in das Ausgabeverzeichnis.

## docker:init

```bash
vendor/bin/flowcrafter docker:init
```

Erzeugt ein `Dockerfile` (FrankenPHP-Basisimage) und eine
`docker-compose.yml` mit Redis, Service, Observer, Scheduler,
Projection-Worker und UI. Die PHP-Extensions werden aus den `ext-*`
Einträgen der `composer.json` abgeleitet; ist nur `ext-pdo` angegeben,
wird nach dem PDO-Treiber gefragt. Siehe [deployment.md](deployment.md).

## dev

```bash
vendor/bin/flowcrafter dev [--host=0.0.0.0] [--port=8000]
```

1. **Preflight:** prüft Config, Service-Index und primären Storage und
   vergleicht die Anzahl der Flows. Bei fehlender Initialisierung oder
   Abweichung werden `storage:init` bzw. `storage:rebuild --clear`
   angeboten.
2. Startet `php -S host:port service/index.php` mit `FLOWCRAFTER_DEV=1`
   (aktiviert `/api/dev/*`) und einen Observer-Subprozess.
3. Fragt, ob Scheduler (Default: nein) und Projection-Worker
   (Default: ja) gestartet werden sollen — nur wenn Schedules bzw.
   Handler gefunden wurden.
4. Überwacht alle Subprozesse: Abgestürzte Prozesse werden neu gestartet,
   bei Änderungen an `.php`-Dateien in den PSR-4-Verzeichnissen des
   Projekts (außerhalb von `vendor/`) werden Observer, Scheduler und
   Projection-Worker neu gestartet. Der API-Server lädt Code ohnehin pro
   Request neu.

Nur für die Entwicklung gedacht.

## service

```bash
vendor/bin/flowcrafter service [--host=] [--port=] [--workers=] [--num-threads=]
```

Erzeugt ein temporäres Caddyfile und startet `frankenphp run` mit
`service/worker.php` im Worker-Modus. Reihenfolge der Werte:
CLI-Option → Config → Default (`0.0.0.0`, `8000`, 4 Worker,
`num-threads = workers × 2`, mindestens `workers + 1`). Das
`frankenphp`-Binary muss im `PATH` liegen.

## observer

```bash
vendor/bin/flowcrafter observer [--workers=1]
```

- `--workers=1`: verarbeitet die Queue im eigenen Prozess. Fehler werden
  geloggt, nach 2 s geht es weiter.
- `--workers=N` (N > 1): startet N Kindprozesse (`observer --workers=1`),
  präfixt deren Ausgabe mit `[worker i]` und startet beendete Worker
  automatisch neu.

## scheduler

```bash
vendor/bin/flowcrafter scheduler
```

Prüft jede Minute alle Schedules und räumt abgelaufene ephemere Flows auf.
Genau **eine** Instanz betreiben.

## projection:worker

```bash
vendor/bin/flowcrafter projection:worker
```

Arbeitet die Projection-Queue ab (Poll-Intervall 1 s). Findet die
Discovery keine Handler, beendet sich der Command sofort erfolgreich.
Ein Fehler außerhalb eines Handlers (z. B. Verbindungsabbruch) beendet
den Prozess — in Produktion mit Restart-Policy betreiben.

## Heartbeats

`observer`, `scheduler` und `projection:worker` schreiben regelmäßig eine
Heartbeat-Datei nach `<sys_temp_dir>/flowcrafter/<typ>.<host>.<pid>.heartbeat`.
`/api/info` und `/metrics` zählen Dateien, die höchstens 60 s alt sind.
Service und Worker müssen dafür dasselbe Temp-Verzeichnis sehen
(in Docker: gemeinsames Volume auf `/tmp/flowcrafter`).
