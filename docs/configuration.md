# Konfiguration
- [Back to README.md](./../README.md)

Erstelle eine `flowcrafter.php` im Projektstamm (oder via
`vendor/bin/flowcrafter config:create`):

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

## Storage-Backends im Überblick

Das Storage-Backend wird über typisierte Config-Objekte konfiguriert:

| Backend         | Config-Klasse                  | Parameter                                          | Besonderheit                              |
| --------------- | ------------------------------ | -------------------------------------------------- | ----------------------------------------- |
| MySQL           | `Storage\Config\MySqlConfig`   | `host`, `port`, `database`, `username`, `password` | Relationales Schema, Transaktionen, PDO — atomarer Queue-Zugriff via `FOR UPDATE SKIP LOCKED` |
| Redis           | `Storage\Config\RedisConfig`   | `host`, `port`                                     | In-Memory, RediSearch-Indizes             |
| EventSourcingDB | `Storage\Config\EsdbConfig`    | `url`, `apiToken`                                  | Event Sourcing, Append-Only — atomarer Queue-Zugriff via Claim-Events (`IsSubjectPristine`) |

Alle drei Backends erben von `Storage\Service` und führen neben dem
primären Backend automatisch eine SQLite-Datenbank (`flow_list`,
`flow_run_list`) als schnellen Lese-Cache für API-Anfragen. Die
SQLite-Datei liegt standardmäßig unter `data/database.sqlite` im
Projektverzeichnis und kann über einen optionalen `sqliteFile`-Parameter
in der Config überschrieben werden.

> **Docker:** Im mitgelieferten `docker-compose.yml` teilen sich `service`
> und `observer` denselben SQLite-Pfad über ein gemeinsames Volume
> (`service-data:/app/data`). SQLite nutzt WAL-Mode für gleichzeitige
> Schreib- und Lesezugriffe beider Container.

**Beispiel MySQL:**
```php
use Wundii\Flowcrafter\Storage\Config\MySqlConfig;
$flowcrafterConfig->setStorageConfig(
    new MySqlConfig('localhost', 3306, 'flowcrafter', 'root', 'secret')
);
```

**Beispiel EventSourcingDB:**
```php
use Wundii\Flowcrafter\Storage\Config\EsdbConfig;
$flowcrafterConfig->setStorageConfig(
    new EsdbConfig('http://localhost:3000', 'my-api-token')
);
```

## Optionale Einstellungen

| Methode                        | Beschreibung                                                                         |
| ------------------------------ | ------------------------------------------------------------------------------------ |
| `setServerHost()`              | Server-Host (Default: `0.0.0.0`)                                                     |
| `setServerPort()`              | Server-Port (Default: `8000`)                                                        |
| `setServerWorkers()`           | Anzahl FrankenPHP-Worker (Default: `4`)                                              |
| `setServerHttps()`             | HTTPS aktivieren für FrankenPHP (Default: `false`)                                   |
| `setServerSecret()`            | Bearer-Token für die API-Authentifizierung (ohne Secret sind alle Routen öffentlich) |
| `setServerDescription()`       | Beschreibung, die über `/api/info` und `/metrics` exponiert wird                     |
| `setDependenciesInjection()`   | Service-Instanzen, die in Stub-Konstruktoren injiziert werden                        |
