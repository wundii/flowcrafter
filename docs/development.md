# Entwicklung
- [Back to README.md](./../README.md)

Hinweise für Contributor an Flowcrafter selbst.

## Quality-Scripts

```bash
composer install     # Abhängigkeiten installieren
composer format      # Rector + ECS automatisch anwenden
composer analyze     # Rector (dry-run) + ECS + PHPStan Level 10
composer stan        # nur PHPStan
composer phplint     # PHP-Lint
composer test        # PHPUnit (benötigt Docker für Testcontainers)
composer coverage    # PHPUnit mit Clover-Coverage (coverage.xml)
composer qa          # analyze + test
composer seed        # Demo-Daten für die UI erzeugen (bin/seed-ui-demo.php)
composer seed:reset  # Demo-Daten zurücksetzen und neu erzeugen
```

Nach jeder PHP-Änderung `composer format` und `composer stan` ausführen.

## Code-Konventionen

- `declare(strict_types=1)` in jeder Datei, PHP ≥ 8.2
- PHPStan Level 10 ohne Baseline
- `readonly` wo möglich
- `array_key_exists()` statt `isset()` für Array-Key-Prüfungen

## Projektstruktur

| Pfad              | Inhalt                                                                         |
|-------------------|--------------------------------------------------------------------------------|
| `src/`            | Library: Builder, Runner, Flow-Modell, Storage, Queue, Scheduler, Projektion  |
| `src/Console/`    | CLI-Commands, Preflight, File-Watcher, Heartbeat                               |
| `src/Testing/`    | `FlowTestCase` / `FlowAssertTrait` für Nutzer der Library                      |
| `service/`        | REST-API: Flower-Router, Controller, Entry-Points (`index.php`, `worker.php`)  |
| `templates/`      | Vorlagen für `config:create` und `docker:init`                                 |
| `tests/`          | PHPUnit-Tests, `MockClass/` (Beispiel-Flows), `Trait/` (Backend-Fixtures)      |

## Tests

- PHPUnit 11, zufällige Reihenfolge, Abbruch beim ersten Fehler.
- Backend-Tests nutzen **Testcontainers**: Redis und MariaDB starten
  einmal pro Testklasse, EventSourcingDB pro Test (langsam).
- Die Backend-Traits in `tests/Trait/` liefern `storage()` (mit
  SQLite `:memory:` als Index) und `queue()`.
- `tests/MockClass/` enthält Flows für alle Sonderfälle (Fail, Retry,
  Bool, RunOnce, Ephemeral, Projection, Enqueue/Run-Trigger,
  Interface-Binding, Loop-/Dangling-Steps, Schedules).

## CI

`.github/workflows/code_quality.yml`:
1. phplint + `composer analyze` auf der kleinsten unterstützten PHP-Version
2. PHPUnit über alle unterstützten PHP-Versionen ≥ 8.2 (Matrix wird per
   `.github/scripts/version_matrix.php` ermittelt)
3. Coverage-Upload zu Codecov

Zusätzlich: `lint-pr-title.yml` (Conventional-Commit-Präfixe im PR-Titel)
und `release.yml` (GitHub-Release bei Tag `v*`).
