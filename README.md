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
- Drei Storage-Backends: **MySQL**, **Redis**, **EventSourceDB**
- Synchrone Ausführung (`FlowRunner`) und asynchrone Queue-Verarbeitung (`FlowObserver`)
- Vollständiges Message- und Exception-Logging pro Flow-Instanz
- REST-API über den integrierten Flower-Micro-Router
- Symfony Console Commands für Init, Observer und Mermaid-Diagramme
- PHPStan Level 10, ECS Code Style, vollständige Integration-Tests mit Testcontainers

---

## Installation

```bash
composer require wundii/flowcrafter
```

---

## Konfiguration

Erstelle eine `flowcrafter.php` im Projektstamm:

```php
<?php

use Wundii\Flowcrafter\Config\FlowcrafterConfig;

return static function (FlowcrafterConfig $config): void {
    $config->setStorageClass('Wundii\Flowcrafter\Storage\MySql');
    $config->setStorageHost('localhost');
    $config->setStoragePort(3306);
    $config->setStorageDatabase('flowcrafter');
    $config->setStorageUsername('user');
    $config->setStoragePassword('password');
    $config->setServerSecret('bearer-token-secret');
    $config->setServerDescription('Mein FlowCrafter Service');
};
```

Beispiele für alle Backends liegen unter `flowcrafter-redis.php` und `flowcrafter-esdb.php`.

### Storage-Backends im Überblick

| Backend       | Klasse | Besonderheit |
|---------------| ------ | ------------ |
| MySQL         | `Storage\MySql` | Relationales Schema, Transaktionen, PDO |
| Redis         | `Storage\Redis` | In-Memory, RediSearch-Indizes |
| EventSourceDB | `Storage\Esdb` | Event Sourcing, Append-Only |

---

## Inbetriebnahme

### 1. Storage initialisieren

```bash
vendor/bin/flowcrafter init
```

Legt alle Tabellen / Indizes im konfigurierten Backend an.

### 2. API-Server starten

```bash
php -S localhost:8000 service/index.php
```

### 3. Observer starten (für asynchrone Queue)

```bash
vendor/bin/flowcrafter observer
```

Der Observer läuft als Daemon, pollt die Queue und verarbeitet Messages asynchron.

---

## Projektstruktur

```
flowcrafter/
├── src/
│   ├── Config/
│   │   └── FlowcrafterConfig.php      # Konfigurationsklasse
│   ├── Console/Commands/
│   │   ├── FlowInitCommand.php        # Storage initialisieren
│   │   ├── FlowCreateCommand.php      # Flow registrieren
│   │   ├── FlowObserverCommand.php    # Observer-Daemon starten
│   │   └── FlowMermaidCommand.php     # Mermaid-Diagramm erzeugen
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
│   │   └── Esdb.php                   # EventStore DB-Implementierung
│   ├── Flow.php                       # Flow-Instanz (Domain Model)
│   ├── FlowSchema.php                 # Workflow-Definition (Blueprint)
│   ├── FlowRunner.php                 # Synchrone Ausführungs-Engine
│   ├── FlowObserver.php               # Asynchroner Queue-Prozessor
│   ├── FlowMessage.php                # Nachrichtenobjekt
│   ├── FlowException.php              # Exception-Objekt mit Kontext
│   ├── FlowRun.php                    # Ausführungsprotokoll
│   └── Stub.php                       # Prozessor-Unit
├── service/
│   ├── Flower/                        # Flower Micro-Router
│   │   ├── Flower.php                 # Singleton: Request-Handling, Auth
│   │   ├── Router.php                 # Routen-Registry (Symfony Routing)
│   │   └── MethodEnum.php             # GET, POST, PUT, DELETE, …
│   └── index.php                      # API-Einstiegspunkt
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

Die REST-API wird über `service/index.php` bereitgestellt (Flower Micro-Router). Alle Endpunkte außer `GET /` erfordern einen Bearer-Token (`setServerSecret()`).

### Flows & Exceptions

| Methode | Pfad | Parameter | Beschreibung |
| ------- | ---- | --------- | ------------ |
| GET | `/api/ping` | — | Verbindungstest (`pong`) |
| GET | `/api/info` | — | Server-Info + Observer-Status |
| GET | `/api/flows` | `sort`, `top`, `source` | Alle Flow-Instanzen |
| GET | `/api/flows/detail` | `hash` oder `runtimeHash` | Flow mit Messages & Exceptions |
| GET | `/api/exceptions` | `sort`, `top`, `flowHash` | Alle Exceptions |

### Ausführung & Queue

| Methode | Pfad | Body | Beschreibung |
| ------- | ---- | ---- | ------------ |
| POST | `/api/flows/run` | `{ flowHash, messageSource, message }` | Flow synchron ausführen |
| POST | `/api/queue` | `{ flowHash, messageSource, message }` | Flow in die Queue stellen |
| GET | `/api/queue/count` | — | Aktuelle Queue-Größe |

---

## Console Commands

```bash
# Storage-Tabellen / -Indizes anlegen
vendor/bin/flowcrafter init

# Flow-Schema registrieren
vendor/bin/flowcrafter create App\\MyFlow

# Observer-Daemon starten
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
