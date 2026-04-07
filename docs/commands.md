# Console Commands
- [Back to README.md](./../README.md)

```bash
# Konfigurationsdatei (flowcrafter.php) erzeugen
vendor/bin/flowcrafter config:create

# Storage-Tabellen / -Indizes anlegen
vendor/bin/flowcrafter storage:init

# Entwicklung: API-Server + Observer + Scheduler zusammen starten
vendor/bin/flowcrafter dev [--host=0.0.0.0] [--port=8000]

# Produktion: API-Server (FrankenPHP Worker Mode)
vendor/bin/flowcrafter service [--host=0.0.0.0] [--port=8000] [--workers=4]

# Observer-Worker starten (ein oder mehrere)
vendor/bin/flowcrafter observer [--workers=1]

# Scheduler für zeitgesteuerte Flow-Auslösung starten
vendor/bin/flowcrafter scheduler

# Dockerfiles + docker-compose.yml generieren
vendor/bin/flowcrafter docker:init

# Mermaid-Diagramm für einen Flow generieren
vendor/bin/flowcrafter diagram:mermaid App\\MyFlow [--output=./]

# SQLite-Cache aus dem primären Backend neu aufbauen (z. B. nach Deployment)
vendor/bin/flowcrafter storage:rebuild [--clear]
```

> `storage:rebuild` iteriert alle Flow-Hashes aus dem primären Backend
> und schreibt die Flow-Daten in die SQLite neu. Mit `--clear` wird die
> SQLite vor dem Rebuild geleert.
