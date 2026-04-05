# Entwicklung
- [Back to README.md](./../README.md)

Quality-Scripts für Contributor:

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
