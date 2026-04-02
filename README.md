# flowcrafter

[![PHP-Tests](https://img.shields.io/github/actions/workflow/status/wundii/flowcrafter/code_quality.yml?branch=main&style=for-the-badge)](https://github.com/wundii/flowcrafter/actions/workflows/code_quality.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%2010-brightgreen.svg?style=for-the-badge)](https://phpstan.org/)
![VERSION](https://img.shields.io/packagist/v/wundii/flowcrafter?style=for-the-badge)
[![PHP](https://img.shields.io/packagist/php-v/wundii/flowcrafter?style=for-the-badge)](https://www.php.net/)
[![Rector](https://img.shields.io/badge/Rector-8.2-blue.svg?style=for-the-badge)](https://getrector.com)
[![ECS](https://img.shields.io/badge/ECS-check-blue.svg?style=for-the-badge)](https://tomasvotruba.com/blog/zen-config-in-ecs)
[![PHPUnit](https://img.shields.io/badge/PHP--Unit-check-blue.svg?style=for-the-badge)](https://phpunit.org)
[![codecov](https://img.shields.io/codecov/c/github/wundii/flowcrafter/main?token=TNC2MM0MWS&style=for-the-badge)](https://codecov.io/github/wundii/flowcrafter)
[![Downloads](https://img.shields.io/packagist/dt/wundii/flowcrafter.svg?style=for-the-badge)](https://packagist.org/packages/wundii/flowcrafter)

PHP-Bibliothek zur Definition, Ausführung und Überwachung nachrichtengetriebener Workflows (State Machines). Flows werden als typsichere PHP-Klassen definiert und über austauschbare Storage-Backends persistiert.

## Features

- Typsichere Workflow-Definitionen via PHP-Interfaces
- Drei Storage-Backends: **MySQL**, **Redis**, **EventSourcingDB**
- **SQLite Service-Layer**: Alle drei Backends führen eine SQLite-Zusammenfassung (`flow_list`, `flow_run_list`) als schnellen Query-Cache für die API — kein Read-Zugriff auf das primäre Backend nötig
- Synchrone Ausführung (`FlowRunner`) und asynchrone Queue-Verarbeitung (`FlowObserver`)
- Vollständiges Message- und Exception-Logging pro Flow-Instanz
- **Flow-Status**: Jeder Flow trägt einen berechneten Status (`IN_PROGRESS`, `IN_PROGRESS_EXCEEDED`, `OK`, `WARNING`, `FAILED`) basierend auf Leaf-Stubs, FlowResults und Exceptions
- Stub-Source-Snapshotting: Quellcode der Stubs wird bei Ausführung gespeichert und kann mit dem aktuellen Stand verglichen werden
- Schema-Versionierung: Flow-Schema wird per MD5 gehasht, nicht ausführbare Flows werden erkannt
- REST-API über den integrierten Flower-Micro-Router (synchrone Ausführung, Queue-Management, Schema-Inspektion)
- Prometheus / OpenMetrics Monitoring (`/metrics`)
- Dependency Injection: Service-Instanzen in Stub-Konstruktoren via Symfony DI Container
- Symfony Console Commands für Init, Observer, Serve, Rebuild und Mermaid-Diagramme
- PHPStan Level 10, ECS Code Style, vollständige Integration-Tests mit Testcontainers

---

## Installation

```bash
composer require wundii/flowcrafter
```

---

## Konfiguration

Erstelle eine `flowcrafter.php` im Projektstamm (oder via `vendor/bin/flowcrafter create`):

```php
<?php

declare(strict_types=1);

use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Storage\Config\RedisConfig;

return static function (FlowcrafterConfig $flowcrafterConfig): void {
    $flowcrafterConfig->setStorageConfig(new RedisConfig('localhost', 6379));
    $flowcrafterConfig->setServerHost('0.0.0.0');
    $flowcrafterConfig->setServerPort(8000);
    $flowcrafterConfig->setServerWorkers(4);
    $flowcrafterConfig->setServerHttps(false);
    $flowcrafterConfig->setServerSecret();
    $flowcrafterConfig->setServerDescription();
    $flowcrafterConfig->setDependenciesInjection();
};
```

### Storage-Backends im Überblick

Das Storage-Backend wird über typisierte Config-Objekte konfiguriert:

| Backend         | Config-Klasse                  | Parameter                                    | Besonderheit                              |
| --------------- | ------------------------------ | -------------------------------------------- | ----------------------------------------- |
| MySQL           | `Storage\Config\MySqlConfig`   | `host`, `port`, `database`, `username`, `password` | Relationales Schema, Transaktionen, PDO — atomarer Queue-Zugriff via `FOR UPDATE SKIP LOCKED` |
| Redis           | `Storage\Config\RedisConfig`   | `host`, `port`                               | In-Memory, RediSearch-Indizes             |
| EventSourcingDB | `Storage\Config\EsdbConfig`    | `url`, `apiToken`                            | Event Sourcing, Append-Only — atomarer Queue-Zugriff via Claim-Events (`IsSubjectPristine`) |

Alle drei Backends erben von `Storage\Service` und führen neben dem primären Backend automatisch eine SQLite-Datenbank (`flow_list`, `flow_run_list`) als schnellen Lese-Cache für API-Anfragen. Die SQLite-Datei liegt standardmäßig unter `data/database.sqlite` im Projektverzeichnis und kann über einen optionalen `sqliteFile`-Parameter in der Config überschrieben werden.

> **Docker:** Im mitgelieferten `docker-compose.yml` teilen sich `service` und `observer` denselben SQLite-Pfad über ein gemeinsames Volume (`service-data:/app/data`). SQLite nutzt WAL-Mode für gleichzeitige Schreib- und Lesezugriffe beider Container.

**Beispiel MySQL:**
```php
use Wundii\Flowcrafter\Storage\Config\MySqlConfig;
$flowcrafterConfig->setStorageConfig(new MySqlConfig('localhost', 3306, 'flowcrafter', 'root', 'secret'));
```

**Beispiel EventSourcingDB:**
```php
use Wundii\Flowcrafter\Storage\Config\EsdbConfig;
$flowcrafterConfig->setStorageConfig(new EsdbConfig('http://localhost:3000', 'my-api-token'));
```

### Optionale Einstellungen

| Methode                        | Beschreibung                                                                         |
| ------------------------------ | ------------------------------------------------------------------------------------ |
| `setServerHost()`              | Server-Host (Default: `0.0.0.0`)                                                    |
| `setServerPort()`              | Server-Port (Default: `8000`)                                                        |
| `setServerWorkers()`           | Anzahl FrankenPHP-Worker (Default: `4`)                                              |
| `setServerHttps()`             | HTTPS aktivieren für FrankenPHP (Default: `false`)                                   |
| `setServerSecret()`            | Bearer-Token für die API-Authentifizierung (ohne Secret sind alle Routen öffentlich) |
| `setServerDescription()`       | Beschreibung, die über `/api/info` und `/metrics` exponiert wird                     |
| `setDependenciesInjection()`   | Service-Instanzen, die in Stub-Konstruktoren injiziert werden                        |

---

## Inbetriebnahme

### 1. Config Datei erstellen und konfigurieren

```bash
vendor/bin/flowcrafter create
```

### 2. Storage initialisieren

```bash
vendor/bin/flowcrafter init
```

Legt alle Tabellen / Indizes im konfigurierten Backend sowie die SQLite-Tabellen (`flow_list`, `flow_run_list`) an.

> **Hinweis:** `frankenphp:service` und `observer` rufen `initializeDatabase()` beim Start automatisch auf — ein manuelles `init` ist nur beim ersten Setup oder nach manuellen Schema-Änderungen nötig.

### 3. Entwicklung: API-Server + Observer starten

```bash
vendor/bin/flowcrafter dev
```

Startet den PHP-Built-in-Server und den Observer zusammen in einem Kommando. Ctrl+C beendet beide Prozesse. Nur für Entwicklung gedacht.

| Option   | Default   | Beschreibung |
| -------- | --------- | ------------ |
| `--host` | `0.0.0.0` | Server-Host  |
| `--port` | `8000`    | Server-Port  |

### 4. Produktion: FrankenPHP + Docker

Für den Produktionsbetrieb werden API-Server und Observer als getrennte Container betrieben.

**Docker-Dateien generieren:**

```bash
vendor/bin/flowcrafter docker:init
```

Erzeugt `Dockerfile.service`, `Dockerfile.observer` und `docker-compose.yml` im Projektstamm.

**Einzeln starten (ohne Docker):**

```bash
# API-Server (FrankenPHP Worker Mode)
vendor/bin/flowcrafter frankenphp:service [--host=0.0.0.0] [--port=8000] [--workers=4]

# Observer (ein oder mehrere Worker)
vendor/bin/flowcrafter observer [--workers=1]
```

| Container    | Command                         | Skalierung                                    |
| ------------ | ------------------------------- | --------------------------------------------- |
| **service**  | `vendor/bin/flowcrafter frankenphp:service` | vertikal (FrankenPHP Worker)         |
| **observer** | `vendor/bin/flowcrafter observer --workers N` | horizontal (mehrere Worker-Prozesse) |

> **Hinweis:** Horizontale Skalierung des Observers erfordert atomaren Queue-Zugriff im Storage-Backend. **MySQL** nutzt `SELECT ... FOR UPDATE SKIP LOCKED`, **EventSourcingDB** nutzt Claim-Events mit `IsSubjectPristine`-Precondition. Bei eigenen `StorageInterface`-Implementierungen liegt die Verantwortung beim Nutzer.

---

## Konzepte

### Flow

Ein Flow ist eine Workflow-Instanz, identifiziert durch einen `flowHash` (UUIDv7) und einen `flowRuntimeHash` (UUIDv7 je Ausführung). Pro Flow werden alle Messages, Exceptions, Runs und Results persistiert. Ein optionales `flowSubject` erlaubt die Beschriftung von Flow-Instanzen. Jeder Flow besitzt einen `flowType` (z. B. `flow.example.v1`) und einen `lastTerm`-Zeitstempel des letzten Runs.

### Flow-Status

Jeder Flow trägt einen berechneten Status, der beim Lesen aus der SQLite (`flow_list`) gespeichert und bei jedem `saveFlow()` aktualisiert wird:

| Status                  | Wert | Bedingung                                                                      |
| ----------------------- | ---- | ------------------------------------------------------------------------------ |
| `IN_PROGRESS`           | 0    | Runs vorhanden, aber noch nicht alle Leaf-Stubs im letzten Run erreicht        |
| `IN_PROGRESS_EXCEEDED`  | 1    | `IN_PROGRESS` + letzter Run liegt > 1 Stunde zurück                           |
| `OK`                    | 2    | Alle relevanten Leaf-Stubs erreicht, keine Exceptions, keine `false`-Results   |
| `WARNING`               | 3    | Alle Leaf-Stubs erreicht, aber mindestens ein `FlowResult` mit `result = false` |
| `FAILED`                | 4    | Mindestens eine `FlowException` im letzten Run                                 |

**Leaf-Stubs** sind Stubs, deren Rückgabetypen von keinem weiteren Stub konsumiert werden (Endknoten des Workflow-Graphen). Erst wenn alle relevanten Leaf-Stubs im letzten Run Messages erhalten haben, gilt der Flow als abgeschlossen.

Bei partiellen Re-Runs mit `includeStubs` werden nur diejenigen Leaf-Stubs geprüft, die im letzten Run tatsächlich Messages empfangen haben — Leaf-Stubs aus anderen Zweigen (z. B. bei AND-Joins, deren zweite Eingabe fehlt) werden ignoriert.

### FlowSchema

Das Schema definiert den Workflow-Aufbau: welche `StubInterface`-Implementierungen existieren, welche Nachrichtentypen sie konsumieren und welcher Message-Typ den Flow initialisiert bzw. abschließt. Das Schema wird per MD5 gehasht — stimmt der aktuelle Hash nicht mit dem gespeicherten `flowSchemaHash` überein, gilt der Flow als nicht ausführbar (`isExecutable = false`).

### Messages & State Transitions

| Zustand   | Bedeutung                                     |
| --------- | --------------------------------------------- |
| `WAIT`    | Message wartet auf weitere Inputs im Stub     |
| `PROCESS` | Alle Inputs vorhanden, Stub wird ausgeführt   |
| `FINISH`  | Message wurde verarbeitet                     |

Ein Stub kann zurückgeben:
- `MessageInterface` → Flow läuft weiter
- `MessageReturnInterface` → Flow endet
- `bool` → Wird als `FlowResult` persistiert (pro Stub-Ausführung mit `flowHash`, `flowRuntimeHash`, `stubSource`, `stubHash`, `result`, `time`)

### Selektive Stub-Ausführung (`includeStubs`)

Wenn eine Message-Klasse von mehreren Stubs konsumiert wird, können beim Auslösen eines Runs gezielt einzelne Stubs ausgewählt werden. Der optionale Parameter `includeStubs` (Array von Stub-Klassennamen) steuert, welche Stubs ausgeführt werden:

- **Leeres Array** (Default): Alle Stubs werden wie gewohnt ausgeführt, alle Leaf-Stubs müssen erreicht werden
- **Nicht-leeres Array**: Nur die aufgeführten Stubs werden ausgeführt. Die Status-Berechnung berücksichtigt nur Leaf-Stubs, die im letzten Run tatsächlich Messages empfangen haben — alle anderen werden ignoriert

Dies gilt sowohl für synchrone Ausführung (`/api/flows/run`) als auch für die Queue (`/api/queue`).

### Observer (asynchrone Verarbeitung)

`appendObserveItem()` legt eine Message in die Queue. Der optionale Parameter `flowSubject` wird dabei durchgereicht und beim Erstellen des Flows als Beschriftung gesetzt. Der `FlowObserver`-Daemon pollt `observeQueue()`, deserialisiert die Messages und führt sie via `FlowRunner` aus. Exceptions werden protokolliert, der Observer läuft mit 2s Retry-Delay weiter.

### Stub-Source-Snapshotting

Bei jeder Flow-Ausführung wird der Quellcode der beteiligten Stubs als `StubSourceEntity` gespeichert. Über die API kann der historische Snapshot mit dem aktuellen Dateiinhalt verglichen werden (`current: true/false`).

---

## API-Endpunkte

Die REST-API wird über `service/index.php` bereitgestellt (Flower Micro-Router). Alle Endpunkte außer `GET /` und `GET /metrics` erfordern einen Bearer-Token, sofern ein `serverSecret` konfiguriert ist.

### Flows & Exceptions

| Methode | Pfad                  | Parameter                                        | Beschreibung                          |
| ------- | --------------------- | ------------------------------------------------ | ------------------------------------- |
| GET     | `/api/ping`           | —                                                | Verbindungstest (`pong`)              |
| GET     | `/api/info`           | —                                                | Server-Beschreibung + Observer-Status |
| GET     | `/api/flows`          | `sort`, `top`, `skip`, `type`, `from`, `to`      | Flow-Instanzen (paginiert, filterbar) |
| GET     | `/api/flows/detail`   | `hash` oder `runtimeHash`                        | Flow mit Messages, Exceptions & Runs  |
| GET     | `/api/flows/stats`    | `from`, `to`, `type`                             | Tägliche Flow-Statistiken (Instanzen & Runs) |
| GET     | `/api/flows/search`   | `subject`, `top`                                 | Flows nach `flowSubject` suchen       |
| GET     | `/api/exceptions`     | `sort`, `top`, `skip`, `flowHash`, `from`, `to`  | Exceptions (paginiert, filterbar)     |

### Schemas & Stub-Source

| Methode | Pfad                       | Parameter                   | Beschreibung                             |
| ------- | -------------------------- | --------------------------- | ---------------------------------------- |
| GET     | `/api/schemas`             | —                           | Alle registrierten Flow-Schemas          |
| GET     | `/api/schema/stub-source`  | `className` oder `stubHash` | Stub-Quellcode (aktuell oder historisch) |
| GET     | `/api/schema/stub-sources` | `stubSource`                | Alle historischen Snapshots eines Stubs  |

### Ausführung & Queue

| Methode | Pfad               | Body / Parameter                                            | Beschreibung                    |
| ------- | ------------------ | ----------------------------------------------------------- | ------------------------------- |
| POST    | `/api/flows/run`   | `{ flowHash, messageSource, message, includeStubs? }`                                  | Flow synchron ausführen         |
| POST    | `/api/queue`       | `{ flowHash?, messageSource, message, includeStubs?, type?, flowSource?, flowSubject? }` | Message in die Queue stellen    |
| GET     | `/api/queues`      | `sort`                                                      | Alle Queue-Einträge mit Details |
| GET     | `/api/queue/count` | —                                                           | Aktuelle Queue-Größe            |

### Monitoring

| Methode | Pfad       | Auth  | Beschreibung                        |
| ------- | ---------- | ----- | ----------------------------------- |
| GET     | `/metrics` | keine | Prometheus / OpenMetrics Exposition |

### Pagination

Die Endpunkte `/api/flows` und `/api/exceptions` unterstützen Paginierung:

| Parameter | Default | Beschreibung                 |
| --------- | ------- | ---------------------------- |
| `top`     | `1000`  | Maximale Einträge (1–10.000) |
| `skip`    | `0`     | Offset für Paginierung       |
| `sort`    | `desc`  | Sortierung (`asc` / `desc`)  |
| `from`    | —       | Startdatum (RFC 3339)        |
| `to`      | —       | Enddatum (RFC 3339)          |

Antwortformat: `{ items, total, hasMore }`

---

## Monitoring (Prometheus / OpenMetrics)

Der Endpunkt `GET /metrics` gibt Metriken im [Prometheus-Textformat](https://prometheus.io/docs/instrumenting/exposition_formats/) (Version 0.0.4) zurück und ist ohne Authentication erreichbar. Die Absicherung erfolgt auf Netzwerkebene (Firewall, Reverse Proxy).

**Exportierte Metriken:**

| Metrik                         | Typ   | Beschreibung                                                              |
| ------------------------------ | ----- | ------------------------------------------------------------------------- |
| `flowcrafter_info`             | gauge | Immer `1`, Labels `description` und `storage` enthalten Metadaten        |
| `flowcrafter_observer_up`      | gauge | `1` = Observer läuft, `0` = Observer gestoppt                             |
| `flowcrafter_observer_workers` | gauge | Anzahl der aktiven Observer-Worker-Prozesse                               |
| `flowcrafter_queue_size`       | gauge | Aktuelle Anzahl der Einträge in der Queue                                 |
| `flowcrafter_flows_total`      | gauge | Gesamtanzahl aller Flow-Instanzen                                         |
| `flowcrafter_exceptions_7d`    | gauge | Anzahl der Exceptions in den letzten 7 Tagen                              |

**Beispielausgabe:**

```
# HELP flowcrafter_info FlowCrafter service information
# TYPE flowcrafter_info gauge
flowcrafter_info{description="Production",storage="Redis"} 1
# HELP flowcrafter_observer_up Whether the FlowCrafter observer process is running (1 = up, 0 = down)
# TYPE flowcrafter_observer_up gauge
flowcrafter_observer_up 1
# HELP flowcrafter_observer_workers Number of active observer worker processes
# TYPE flowcrafter_observer_workers gauge
flowcrafter_observer_workers 2
# HELP flowcrafter_queue_size Number of items currently pending in the queue
# TYPE flowcrafter_queue_size gauge
flowcrafter_queue_size 0
# HELP flowcrafter_flows_total Total number of flow instances
# TYPE flowcrafter_flows_total gauge
flowcrafter_flows_total 42
# HELP flowcrafter_exceptions_7d Number of exceptions in the last 7 days
# TYPE flowcrafter_exceptions_7d gauge
flowcrafter_exceptions_7d 3
```

### Prometheus-Konfiguration

```yaml
scrape_configs:
  - job_name: flowcrafter
    static_configs:
      - targets: ['localhost:8000']
    metrics_path: /metrics
```

### CheckMK

In CheckMK den **Prometheus Special Agent** oder einen **HTTP-Check** auf `/metrics` einrichten. Das Format wird nativ als Prometheus-Exposition erkannt.

---

## Console Commands

```bash
# Konfigurationsdatei (flowcrafter.php) erzeugen
vendor/bin/flowcrafter create

# Storage-Tabellen / -Indizes anlegen
vendor/bin/flowcrafter init

# Entwicklung: API-Server + Observer zusammen starten
vendor/bin/flowcrafter dev [--host=0.0.0.0] [--port=8000]

# Produktion: API-Server (FrankenPHP Worker Mode)
vendor/bin/flowcrafter frankenphp:service [--host=0.0.0.0] [--port=8000] [--workers=4]

# Observer-Worker starten (ein oder mehrere)
vendor/bin/flowcrafter observer [--workers=1]

# Dockerfiles + docker-compose.yml generieren
vendor/bin/flowcrafter docker:init

# Mermaid-Diagramm für einen Flow generieren
vendor/bin/flowcrafter mermaid App\\MyFlow [--output=./]

# SQLite-Cache aus dem primären Backend neu aufbauen (z. B. nach Deployment)
vendor/bin/flowcrafter service:rebuild [--clear]
```

> `service:rebuild` iteriert alle Flow-Hashes aus dem primären Backend und schreibt die Flow-Daten in die SQLite neu. Mit `--clear` wird die SQLite vor dem Rebuild geleert.

---

## Web-UI

Das optionale Web-Frontend [FlowCrafter UI](https://github.com/wundii/flowcrafter-ui) visualisiert Flows, Messages, Exceptions und Queues in Echtzeit. Es kann als Docker-Image gestartet werden:

```bash
docker run -p 3000:3000 -v ./data:/flowcrafter/data wundii/flowcrafter-ui:latest
```

Weitere Informationen im [FlowCrafter UI Repository](https://github.com/wundii/flowcrafter-ui).

---

## Entwicklung

```bash
# Abhängigkeiten installieren
composer install

# Statische Analyse (Rector + ECS + PHPStan Level 10)
composer analyze

# Code Style automatisch korrigieren
composer format

# PHPStan einzeln ausführen
composer stan

# PHP-Lint
composer phplint

# Tests ausführen (benötigt Docker für Testcontainers)
composer test

# Analyse + Tests zusammen
composer qa
```
