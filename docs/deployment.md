# Deployment (Produktion)
- [Back to README.md](./../README.md)

Für den Produktionsbetrieb werden API-Server, Observer und Scheduler als
getrennte Container betrieben.

## Docker-Dateien generieren

```bash
vendor/bin/flowcrafter docker:init
```

Erzeugt `Dockerfile`, `docker-compose.yml` im Projektstamm mit
Service-, Observer- und Scheduler-Container.

## Einzeln starten (ohne Docker)

```bash
# API-Server (FrankenPHP Worker Mode)
vendor/bin/flowcrafter service [--host=0.0.0.0] [--port=8000] [--workers=4]

# Observer (ein oder mehrere Worker)
vendor/bin/flowcrafter observer [--workers=1]

# Scheduler (zeitgesteuerte Flow-Auslösung)
vendor/bin/flowcrafter scheduler
```

## Container-Übersicht

| Container     | Command                                        | Skalierung                              |
| ------------- | ---------------------------------------------- | --------------------------------------- |
| **service**   | `vendor/bin/flowcrafter service`               | vertikal (FrankenPHP Worker)            |
| **observer**  | `vendor/bin/flowcrafter observer --workers N`  | horizontal (mehrere Worker-Prozesse)    |
| **scheduler** | `vendor/bin/flowcrafter scheduler`             | einzelne Instanz (keine Skalierung)     |

> **Hinweis:** Horizontale Skalierung des Observers erfordert atomaren
> Queue-Zugriff im Storage-Backend. **MySQL** nutzt
> `SELECT ... FOR UPDATE SKIP LOCKED`, **EventSourcingDB** nutzt
> Claim-Events mit `IsSubjectPristine`-Precondition. Bei eigenen
> `StorageInterface`-Implementierungen liegt die Verantwortung beim Nutzer.

> **Hinweis:** Der Scheduler darf nur als einzelne Instanz laufen.
> Mehrere Instanzen würden zu doppelten Ausführungen führen.
