# Getting Started
- [Back to README.md](./../README.md)

## 1. Config Datei erstellen und konfigurieren

```bash
vendor/bin/flowcrafter config:create
```

Details zur Konfiguration siehe [configuration.md](configuration.md).

## 2. Storage initialisieren

```bash
vendor/bin/flowcrafter storage:init
```

Legt alle Tabellen / Indizes im konfigurierten Backend sowie die
SQLite-Tabellen (`flow_list`, `flow_run_list`) an.

> **Hinweis:** `service` und `observer` rufen `initializeDatabase()` beim
> Start automatisch auf — ein manuelles `storage:init` ist nur beim ersten
> Setup oder nach manuellen Schema-Änderungen nötig.

## 3. Entwicklung: API-Server + Observer + Scheduler starten

```bash
vendor/bin/flowcrafter dev
```

Startet den PHP-Built-in-Server, den Observer und den Scheduler zusammen
in einem Kommando. Ctrl+C beendet alle Prozesse. Nur für Entwicklung gedacht.

| Option   | Default   | Beschreibung |
|----------|-----------|--------------|
| `--host` | `0.0.0.0` | Server-Host  |
| `--port` | `8000`    | Server-Port  |

Für den Produktionsbetrieb siehe [deployment.md](deployment.md).
