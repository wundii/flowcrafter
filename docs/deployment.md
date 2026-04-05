# Deployment (Produktion)
- [Back to README.md](./../README.md)

Für den Produktionsbetrieb werden API-Server und Observer als getrennte
Container betrieben.

## Docker-Dateien generieren

```bash
vendor/bin/flowcrafter docker:init
```

Erzeugt `Dockerfile.service`, `Dockerfile.observer` und
`docker-compose.yml` im Projektstamm.

## Einzeln starten (ohne Docker)

```bash
# API-Server (FrankenPHP Worker Mode)
vendor/bin/flowcrafter service [--host=0.0.0.0] [--port=8000] [--workers=4]

# Observer (ein oder mehrere Worker)
vendor/bin/flowcrafter observer [--workers=1]
```

## Container-Übersicht

| Container    | Command                                        | Skalierung                              |
| ------------ | ---------------------------------------------- | --------------------------------------- |
| **service**  | `vendor/bin/flowcrafter service`               | vertikal (FrankenPHP Worker)            |
| **observer** | `vendor/bin/flowcrafter observer --workers N`  | horizontal (mehrere Worker-Prozesse)    |

> **Hinweis:** Horizontale Skalierung des Observers erfordert atomaren
> Queue-Zugriff im Storage-Backend. **MySQL** nutzt
> `SELECT ... FOR UPDATE SKIP LOCKED`, **EventSourcingDB** nutzt
> Claim-Events mit `IsSubjectPristine`-Precondition. Bei eigenen
> `StorageInterface`-Implementierungen liegt die Verantwortung beim Nutzer.
