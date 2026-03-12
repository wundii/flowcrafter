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
- Synchrone Ausführung (`FlowRunner`) und asynchrone Queue-Verarbeitung (`FlowObserver`)
- Vollständiges Message- und Exception-Logging pro Flow-Instanz
- REST-API über den integrierten Flower-Micro-Router
- Prometheus / OpenMetrics Monitoring (`/metrics`)
- Symfony Console Commands für Init, Observer, Serve und Mermaid-Diagramme
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

return static function (FlowcrafterConfig $flowcrafterConfig): void {
    $flowcrafterConfig->setStorageClass('Wundii\Flowcrafter\Storage\Redis');
    $flowcrafterConfig->setStorageUrl();
    $flowcrafterConfig->setStorageApiToken();
    $flowcrafterConfig->setStorageHost('localhost');
    $flowcrafterConfig->setStoragePort(6379);
    $flowcrafterConfig->setStorageUsername();
    $flowcrafterConfig->setStoragePassword();
    $flowcrafterConfig->setStorageDatabase();
    $flowcrafterConfig->setServerSecret();
    $flowcrafterConfig->setServerDescription();
};
```

### Storage-Backends im Überblick

| Backend        | Klasse          | Besonderheit                      |
| -------------- | --------------- | --------------------------------- |
| MySQL          | `Storage\MySql` | Relationales Schema, Transaktionen, PDO |
| Redis          | `Storage\Redis` | In-Memory, RediSearch-Indizes     |
| EventSourcingDB | `Storage\Esdb` | Event Sourcing, Append-Only       |

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

Legt alle Tabellen / Indizes im konfigurierten Backend an.

### 3. API-Server + Observer starten

```bash
vendor/bin/flowcrafter serve
```

Startet den API-Server und den Observer zusammen in einem Kommando. Ctrl+C beendet beide Prozesse.

| Option | Default | Beschreibung |
| ------ | ------- | ------------ |
| `--host` | `0.0.0.0` | Server-Host |
| `--port` | `8000` | Server-Port |

**Alternativ einzeln starten:**

```bash
# Nur API-Server
php -S localhost:8000 service/index.php

# Nur Observer
vendor/bin/flowcrafter observer
```

---

## Projektstruktur

```
flowcrafter/
├── bin/
│   └── flowcrafter                    # CLI-Einstiegspunkt (Symfony Console)
├── src/
│   ├── Bootstrap/                     # Config-Auflösung & Initialisierung
│   ├── Config/
│   │   └── FlowcrafterConfig.php      # Konfigurationsklasse
│   ├── Console/
│   │   ├── Commands/
│   │   │   ├── FlowCreateCommand.php  # Konfigurationsdatei erzeugen
│   │   │   ├── FlowInitCommand.php    # Storage initialisieren
│   │   │   ├── FlowMermaidCommand.php # Mermaid-Diagramm erzeugen
│   │   │   ├── FlowObserverCommand.php # Observer-Daemon starten
│   │   │   └── FlowServeCommand.php   # API-Server + Observer starten
│   │   └── Output/                    # Console-Output-Helfer
│   ├── Enum/                          # Message- und Sort-Enums
│   ├── Interface/
│   │   ├── StorageInterface.php       # Backend-Abstraktion
│   │   ├── FlowInterface.php          # Flow-Implementierungsvertrag
│   │   ├── MessageInterface.php       # Basistyp für alle Messages
│   │   ├── MessageInitInterface.php   # Marker: Startnachricht
│   │   ├── MessageDataInterface.php   # Marker: Datennachricht
│   │   ├── MessageReturnInterface.php # Marker: Rückgabewert (Flow-Ende)
│   │   └── StubInterface.php          # Prozessoreinheit
│   ├── Storage/
│   │   ├── MySql.php                  # MySQL-Implementierung
│   │   ├── Redis.php                  # Redis-Implementierung
│   │   └── Esdb.php                   # EventSourcingDB-Implementierung
│   ├── Flow.php                       # Flow-Instanz (Domain Model)
│   ├── FlowBuilder.php               # Builder für Flow-Konstruktion
│   ├── FlowSchema.php                 # Workflow-Definition (Blueprint)
│   ├── FlowRunner.php                 # Synchrone Ausführungs-Engine
│   ├── FlowObserver.php               # Asynchroner Queue-Prozessor
│   ├── FlowMessage.php                # Nachrichtenobjekt
│   ├── FlowException.php              # Exception-Objekt mit Kontext
│   ├── FlowRun.php                    # Ausführungsprotokoll
│   ├── ObserveItem.php                # Queue-Eintrag
│   └── Stub.php                       # Prozessor-Unit
├── service/
│   ├── Flower/                        # Flower Micro-Router
│   │   ├── Flower.php                 # Singleton: Request-Handling, Auth
│   │   ├── Router.php                 # Routen-Registry (Symfony Routing)
│   │   └── MethodEnum.php             # GET, POST, PUT, DELETE, …
│   └── index.php                      # API-Einstiegspunkt
├── templates/
│   └── flowcrafter.php.dist           # Vorlage für flowcrafter create
├── tests/                             # PHPUnit + Testcontainers
└── composer.json
```

---

## Konzepte

### Flow

Ein Flow ist eine Workflow-Instanz, identifiziert durch einen `flowHash` (MD5 des Schemas) und einen `flowRuntimeHash` (UUIDv7 je Ausführung). Pro Flow werden alle Messages, Exceptions und Runs persistiert.

### FlowSchema

Das Schema definiert den Workflow-Aufbau: welche `StubInterface`-Implementierungen existieren, welche Nachrichtentypen sie konsumieren und welcher Message-Typ den Flow initialisiert bzw. abschließt.

### Messages & State Transitions

| Zustand | Bedeutung |
| ------- | --------- |
| `WAIT` | Message wartet auf weitere Inputs im Stub |
| `PROCESS` | Alle Inputs vorhanden, Stub wird ausgeführt |
| `FINISH` | Message wurde verarbeitet |

Ein Stub kann zurückgeben:
- `MessageInterface` → Flow läuft weiter
- `MessageReturnInterface` → Flow endet
- `false` → Keine Aktion

### Observer (asynchrone Verarbeitung)

`appendObserveItem()` legt eine Message in die Queue. Der `FlowObserver`-Daemon pollt `observeQueue()`, deserialisiert die Messages und führt sie via `FlowRunner` aus. Exceptions werden protokolliert, der Observer läuft mit 2s Retry-Delay weiter.

---

## API-Endpunkte

Die REST-API wird über `service/index.php` bereitgestellt (Flower Micro-Router). Alle Endpunkte außer `GET /` und `GET /metrics` erfordern einen Bearer-Token (`setServerSecret()`).

### Flows & Exceptions

| Methode | Pfad | Parameter                                      | Beschreibung                          |
| ------- | ---- |------------------------------------------------|---------------------------------------|
| GET | `/api/ping` | —                                              | Verbindungstest (`pong`)              |
| GET | `/api/info` | —                                              | Server-Info + Observer-Status         |
| GET | `/api/flows` | `sort`, `top`, `skip`, `type`, `from`, `to`    | Flow-Instanzen (paginiert, filterbar) |
| GET | `/api/flows/detail` | `hash` oder `runtimeHash`                      | Flow mit Messages & Exceptions        |
| GET | `/api/exceptions` | `sort`, `top`, `skip`, `flowHash`, `from`, `to` | Exceptions (paginiert, filterbar)     |
| GET | `/api/schemas` |                                    | Flow Schemas                          |
| GET | `/api/schema/stub-source` | `className`                                    | Stub Source                           |

### Ausführung & Queue

| Methode | Pfad | Body / Parameter | Beschreibung |
| ------- | ---- | ---------------- | ------------ |
| POST | `/api/flows/run` | `{ flowHash, messageSource, message }` | Flow synchron ausführen |
| POST | `/api/queue` | `{ flowHash, messageSource, message, type, flowSource }` | Flow in die Queue stellen |
| GET | `/api/queues` | `sort` | Alle Queue-Einträge mit Details |
| GET | `/api/queue/count` | — | Aktuelle Queue-Größe |

### Monitoring

| Methode | Pfad | Auth | Beschreibung |
| ------- | ---- | ---- | ------------ |
| GET | `/metrics` | keine | Prometheus / OpenMetrics Exposition |

---

## Monitoring (Prometheus / OpenMetrics)

Der Endpunkt `GET /metrics` gibt Metriken im [Prometheus-Textformat](https://prometheus.io/docs/instrumenting/exposition_formats/) (Version 0.0.4) zurück und ist ohne Authentication erreichbar. Die Absicherung erfolgt auf Netzwerkebene (Firewall, Reverse Proxy).

**Exportierte Metriken:**

| Metrik | Typ | Beschreibung |
| ------ | --- | ------------ |
| `flowcrafter_info` | gauge | Immer `1`, Label `description` enthält die Server-Beschreibung |
| `flowcrafter_observer_up` | gauge | `1` = Observer läuft, `0` = Observer gestoppt |
| `flowcrafter_queue_size` | gauge | Aktuelle Anzahl der Einträge in der Queue |

**Beispielausgabe:**

```
# HELP flowcrafter_info FlowCrafter service information
# TYPE flowcrafter_info gauge
flowcrafter_info{description="Production"} 1
# HELP flowcrafter_observer_up Whether the FlowCrafter observer process is running (1 = up, 0 = down)
# TYPE flowcrafter_observer_up gauge
flowcrafter_observer_up 1
# HELP flowcrafter_queue_size Number of items currently pending in the queue
# TYPE flowcrafter_queue_size gauge
flowcrafter_queue_size 0
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

# API-Server + Observer zusammen starten
vendor/bin/flowcrafter serve [--host=0.0.0.0] [--port=8000]

# Observer-Daemon einzeln starten
vendor/bin/flowcrafter observer

# Mermaid-Diagramm für einen Flow generieren
vendor/bin/flowcrafter mermaid App\\MyFlow
```

---

## Entwicklung

```bash
# Abhängigkeiten installieren
composer install

# Statische Analyse (PHPStan Level 10) + Code Style prüfen
composer analyze

# Code Style automatisch korrigieren
composer format

# Tests ausführen (benötigt Docker für Testcontainers)
composer test

# Analyse + Tests zusammen
composer qa
```
