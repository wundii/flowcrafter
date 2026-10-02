# Getting Started
- [Back to README.md](./../README.md)

## Voraussetzungen

- PHP ≥ 8.2 mit `ext-pdo` und `ext-pdo_sqlite` (Service-Index)
- Je nach Backend: `ext-redis` + Redis Stack (RedisJSON, RediSearch),
  `ext-pdo_mysql` + MySQL/MariaDB oder `thenativeweb/eventsourcingdb`
  + EventSourcingDB
- Für `vendor/bin/flowcrafter service`: das `frankenphp`-Binary

## 1. Installieren

```bash
composer require wundii/flowcrafter
```

## 2. Config-Datei erstellen

```bash
vendor/bin/flowcrafter config:create
```

Legt `flowcrafter.php` im Projektstamm an (Redis als Storage und Queue).
Details, andere Backends und DI siehe [configuration.md](configuration.md).

## 3. Storage und Queue initialisieren

```bash
vendor/bin/flowcrafter storage:init
```

Legt Tabellen, Indizes bzw. Event-Schemas im Storage-Backend, die
Queue-Strukturen und die SQLite-Tabellen des Service-Index an.

> `observer`, `scheduler` und `service` initialisieren den Storage beim
> Start ebenfalls automatisch. `dev` prüft vorab per Preflight, ob Storage
> und Index bereit und synchron sind, und bietet `storage:init` bzw.
> `storage:rebuild --clear` interaktiv an.

## 4. Ersten Flow schreiben

Messages, Steps und Flow-Klasse — siehe das Beispiel in der
[README](../README.md#beispiel) und die Regeln in
[concepts.md](concepts.md#bausteine-messages-steps-flow). Den Aufbau
prüfen:

```bash
vendor/bin/flowcrafter diagram:mermaid 'App\Flow\OrderFlow'
```

## 5. Entwicklungsumgebung starten

```bash
vendor/bin/flowcrafter dev
```

Startet den PHP-Built-in-Server (API inkl. Dev-Endpunkte), den Observer
und — nach Rückfrage — Scheduler und Projection-Worker. Code-Änderungen
lösen einen automatischen Neustart der Worker aus. Ctrl+C beendet alles.

| Option   | Default   | Beschreibung |
|----------|-----------|--------------|
| `--host` | `0.0.0.0` | Server-Host  |
| `--port` | `8000`    | Server-Port  |

Optional die [Web-UI](../README.md#web-ui) dazu starten.

## 6. Testen

Flows lassen sich ohne Datenbank testen — siehe [testing.md](testing.md).

## 7. Produktion

Getrennte Prozesse/Container für Service, Observer, Scheduler und
Projection-Worker — siehe [deployment.md](deployment.md).
