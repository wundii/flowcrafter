# Konfiguration
- [Back to README.md](./../README.md)

## Inhalt

- [flowcrafter.php](#flowcrafterphp)
- [Wo die Config gesucht wird](#wo-die-config-gesucht-wird)
- [Storage-Backends](#storage-backends)
- [Queue-Backends](#queue-backends)
- [Service-Index (SQLite)](#service-index-sqlite)
- [Server-Einstellungen](#server-einstellungen)
- [Dependency Injection](#dependency-injection)
- [Env-Helper](#env-helper)

## flowcrafter.php

Die Konfiguration ist eine PHP-Datei, die eine Closure zurückgibt. Die
Closure erhält eine `FlowcrafterConfig` als ersten Parameter. Anlegen per
`vendor/bin/flowcrafter config:create`:

```php
<?php

declare(strict_types=1);

use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\DependencyInjection\DependencyRegistry;
use Wundii\Flowcrafter\Env;
use Wundii\Flowcrafter\Queue\Config\RedisQueueConfig;
use Wundii\Flowcrafter\Storage\Config\RedisStorageConfig;

return static function (FlowcrafterConfig $flowcrafterConfig): void {
    $flowcrafterConfig->setStorageConfig(new RedisStorageConfig('localhost', 6379));
    $flowcrafterConfig->setQueueConfig(new RedisQueueConfig('localhost', 6379));
    $flowcrafterConfig->setServerHost('0.0.0.0');
    $flowcrafterConfig->setServerPort(8000);
    $flowcrafterConfig->setServerWorkers(4);
    $flowcrafterConfig->setServerHttps(false);
    $flowcrafterConfig->setServerSecret(Env::string('FLOWCRAFTER_SECRET'));
    $flowcrafterConfig->setServerDescription('Production');
    $flowcrafterConfig->setServerStorage(__DIR__ . '/data/flowcrafter-ui.sqlite');
    $flowcrafterConfig->setDependencyRegistry(new DependencyRegistry());
};
```

## Wo die Config gesucht wird

| Kontext                      | Quelle                                                                                   |
|------------------------------|------------------------------------------------------------------------------------------|
| CLI (`vendor/bin/flowcrafter`) | `--config <pfad>` bzw. `-c <pfad>`, sonst `flowcrafter.php` im aktuellen Arbeitsverzeichnis |
| HTTP-Service (`service/`)    | Umgebungsvariable `FLOWCRAFTER_CONFIG` (aus `$_ENV`, sonst `getenv()`)                   |

Die CLI-Commands `service` und `dev` geben den Pfad automatisch per
`FLOWCRAFTER_CONFIG` an die gestarteten Subprozesse weiter. Schlägt das
Laden fehl, antwortet der Built-in-Server (`dev`) mit `503`; der
FrankenPHP-Worker (`service`) bricht beim Start ab.

> Die CLI ignoriert ein `--config` auf eine nicht existierende Datei
> stillschweigend — bei Problemen den Pfad prüfen.

## Storage-Backends

Das Storage-Backend ist die primäre, dauerhafte Ablage aller Flows.

| Backend          | Config-Klasse                              | Parameter                                          | Implementierung              |
|------------------|--------------------------------------------|----------------------------------------------------|------------------------------|
| MySQL / MariaDB  | `Storage\Config\MySqlStorageConfig`        | `host`, `port`, `database`, `username`, `password` | `Storage\MySqlStorage`       |
| Redis            | `Storage\Config\RedisStorageConfig`        | `host`, `port`, `password = null`                  | `Storage\RedisStorage`       |
| EventSourcingDB  | `Storage\Config\EsdbStorageConfig`         | `url`, `apiToken`                                  | `Storage\EsdbStorage`        |

| Backend         | Voraussetzungen                                    | Ablage                                                                  |
|-----------------|----------------------------------------------------|-------------------------------------------------------------------------|
| MySQL           | `ext-pdo_mysql`                                    | Tabellen `flow_instance`, `flow_run`, `flow_message`, `flow_exception`, `flow_result`, `flow_step_retry`, `flow_schema`, `flow_source_step`, `flow_source_message`, `flow_schedule_exception`, `flow_observer_exception`, `flow_projection_exception` |
| Redis           | `ext-redis`, Redis Stack (RedisJSON + RediSearch)  | JSON-Dokumente unter `flow:*`, RediSearch-Indizes `idx:flow*`           |
| EventSourcingDB | `thenativeweb/eventsourcingdb`                     | Events `flowcrafter.*.v1` unter den Subjects `/flow/<hash>/…`           |

**Beispiele:**

```php
use Wundii\Flowcrafter\Storage\Config\MySqlStorageConfig;
use Wundii\Flowcrafter\Storage\Config\EsdbStorageConfig;

$flowcrafterConfig->setStorageConfig(
    new MySqlStorageConfig('localhost', 3306, 'flowcrafter', 'root', 'secret')
);

$flowcrafterConfig->setStorageConfig(
    new EsdbStorageConfig('http://localhost:3000', 'my-api-token')
);
```

> **MySQL:** Die Tabelle `flow_schema` hat einen Unique-Index auf den
> Flow-Typ. Pro Typ kann es damit nur **eine** Schema-Version geben —
> strukturelle Änderungen erfordern einen neuen Typ (`.v2`).

> **Redis:** `initializeDatabase()` legt die RediSearch-Indizes bei jedem
> Aufruf neu an. Da `observer`, `scheduler` und `service` das beim Start
> tun, kann es direkt nach einem Neustart kurzzeitig unvollständige
> Suchergebnisse geben, solange Redis neu indexiert.

Eigene Backends implementieren `StorageInterface` (am einfachsten durch
Erben von `Storage\ServiceStorage`, das den SQLite-Index mitbringt) und
eine passende `StorageConfigInterface`-Klasse, deren `getStorageClass()`
den Klassennamen liefert. Der Konstruktor erhält `($config, ?string $serverStorage)`.

## Queue-Backends

Die Queue ist unabhängig vom Storage konfigurierbar (eigene Verbindung).
Sie enthält zwei Warteschlangen: die **Observer-Queue** (asynchrone
Flow-Ausführung) und die **Projection-Queue**.

```php
$flowcrafterConfig->setQueueConfig(new RedisQueueConfig('localhost', 6379));
```

| Backend          | Config-Klasse                        | Observer-Queue                                          | Projection-Queue                                                     |
|------------------|--------------------------------------|---------------------------------------------------------|----------------------------------------------------------------------|
| Redis            | `Queue\Config\RedisQueueConfig`      | Liste `flow:queue` (`LPUSH`/`BRPOP`)                    | Stream `flow:projection:queue`, Consumer-Group `projection_workers`  |
| MySQL            | `Queue\Config\MySqlQueueConfig`      | Tabelle `flow_queue` (`FOR UPDATE SKIP LOCKED`)         | Tabelle `projection_queue` (Claim mit 300 s Sichtbarkeit)            |
| EventSourcingDB  | `Queue\Config\EsdbQueueConfig`       | Subject `/flow/queue`, Claim-Events mit `IsSubjectPristine` | Subject `/projection/queue` + Checkpoint `/projection/checkpoint` |
| In-Memory        | — (`Queue\InMemoryQueue` direkt)     | PHP-Array                                               | PHP-Array                                                            |

> **Default:** Ist keine Queue-Config gesetzt, wird **ohne Warnung**
> Redis auf `127.0.0.1:6379` verwendet. In Produktion die Queue immer
> explizit konfigurieren.

| Eigenschaft                         | Redis | MySQL | ESDB |
|-------------------------------------|-------|-------|------|
| Observer horizontal skalierbar      | ja    | ja    | ja   |
| Projection-Worker horizontal skalierbar | ja | ja    | **nein** (kein Claim, gemeinsamer Checkpoint) |
| Zustellung Observer-Queue           | at-most-once | at-most-once | at-most-once |
| Zustellung Projection-Queue         | at-least-once | at-least-once | at-least-once |

> **ESDB-Queue:** Die Untergrenze der offenen Observer-Items wird aus den
> Run-Events des ESDB-Storage abgeleitet. Die ESDB-Queue sollte deshalb
> nur zusammen mit dem ESDB-Storage verwendet werden.

## Service-Index (SQLite)

Alle mitgelieferten Storages erben von `ServiceStorage` und führen neben
dem primären Backend eine **SQLite-Datei** als schnellen Lese-Index für
API-Listen und Statistiken:

| Tabelle                             | Inhalt                                                  |
|-------------------------------------|---------------------------------------------------------|
| `flow_list`                         | eine Zeile pro Flow-Instanz (Typ, Subject, Status, …)   |
| `flow_run_list`                     | Runs                                                    |
| `flow_exception_list`               | Flow-Exceptions                                         |
| `flow_schedule_exception_list`      | Schedule-Exceptions                                     |
| `flow_observer_exception_list`      | Observer-Exceptions                                     |
| `flow_projection_exception_list`    | Projection-Exceptions                                   |
| `flow_ephemeral_list`               | komplette ephemere Flows als JSON (nur hier gespeichert)|

- Pfad über `setServerStorage()`. `null` deaktiviert den Index — Listen,
  Statistiken und ephemere Flows sind dann leer.
- Der Index wird am **Ende** eines Runs aktualisiert. Bricht ein Prozess
  mitten im Run ab, steht der Flow im Primär-Storage, fehlt aber im Index.
- `storage:rebuild` baut die Flows aus dem Primär-Storage neu auf. Schedule-,
  Observer- und Projection-Exceptions sowie ephemere Flows werden dabei
  **nicht** wiederhergestellt.
- Alle Prozesse (Service, Observer, Scheduler, Projection-Worker) müssen
  auf **dieselbe** SQLite-Datei schreiben (in Docker: gemeinsames Volume).
  SQLite läuft im WAL-Modus.

## Server-Einstellungen

| Methode                    | Default                        | Beschreibung                                                                               |
|----------------------------|--------------------------------|--------------------------------------------------------------------------------------------|
| `setServerHost()`          | `0.0.0.0`                      | Bind-Adresse                                                                               |
| `setServerPort()`          | `8000`                         | Port                                                                                       |
| `setServerWorkers()`       | `4`                            | Anzahl persistenter FrankenPHP-Worker                                                      |
| `setServerNumThreads()`    | `workers × 2`                  | Gesamte PHP-Thread-Anzahl; wird automatisch auf mindestens `workers + 1` angehoben         |
| `setServerHttps()`         | `false`                        | HTTPS in FrankenPHP aktivieren                                                             |
| `setServerSecret()`        | `null`                         | Bearer-Token der API. **Ohne Secret sind alle Routen öffentlich.**                         |
| `setServerDescription()`   | `null`                         | Beschreibung für `/api/info` und `/metrics`                                                |
| `setServerStorage()`       | —                              | Pfad zur SQLite-Datei des Service-Index                                                    |
| `setDependencyRegistry()`  | leere Registry                 | Services für Steps, Schedules und Projection-Handler                                       |

Die Umgebungsvariable `FLOWCRAFTER_DEV=1` aktiviert die Dev-Endpunkte
(`/api/dev/*`). `vendor/bin/flowcrafter dev` setzt sie automatisch — in
Produktion **nicht** setzen.

## Dependency Injection

Steps, Schedules und Projection-Handler erhalten Services per
Constructor-Injection über einen Symfony-`ContainerBuilder`. Registriert
wird über eine `DependencyRegistry`:

| Methode                            | Verhalten                                                                      |
|------------------------------------|--------------------------------------------------------------------------------|
| `instance(object)`                 | Fertige Instanz, gebunden an ihre eigene Klasse                                |
| `bind(string $id, object\|class)`  | Interface-Binding: Objekt → Instanz + Alias; Klassenname → Autowiring + Alias  |
| `autowire(class)`                  | Einzelne Klasse per Autowiring                                                 |
| `autowireNamespace(string)`        | Alle instanziierbaren Klassen unter einem PSR-4-Namespace-Präfix               |
| `autowireDirectory(string)`        | Alle instanziierbaren Klassen unter einem Verzeichnis                          |
| `factory(class, Closure, ?alias)`  | Lazy: Closure erhält den PSR-11-Container und liefert den Service              |

```php
use App\Service\HttpClientInterface;
use App\Service\CurlHttpClient;
use Psr\Container\ContainerInterface;
use Wundii\Flowcrafter\DependencyInjection\DependencyRegistry;
use Wundii\Flowcrafter\Env;

$registry = (new DependencyRegistry())
    ->bind(HttpClientInterface::class, new CurlHttpClient())
    ->autowire(MyService::class)
    ->autowireNamespace('App\\Repository')
    ->autowireDirectory(__DIR__ . '/src/Service')
    ->factory(ApiClient::class,
        fn (ContainerInterface $c) => new ApiClient($c->get(HttpClientInterface::class), Env::string('API_KEY')),
    );

$flowcrafterConfig->setDependencyRegistry($registry);
```

**Wichtig zu wissen:**
- Steps, Schedules und Handler werden **nicht geteilt** — pro Ausführung
  (bzw. pro Retry-Versuch) entsteht eine neue Instanz. Registrierte
  Services sind dagegen **geteilt** (Singleton pro Container).
- Für jeden Flow-Run wird ein eigener Container gebaut; Services aus
  `instance()`/`bind(…, object)` sind jedoch dieselben Objekte über alle
  Runs eines Prozesses hinweg. Zustand in solchen Objekten bleibt in
  Langläufern (Observer, FrankenPHP-Worker) erhalten.
- Klassen aus `autowireNamespace()`/`autowireDirectory()` sind private
  Services: Was nicht referenziert wird oder sich nicht autowiren lässt,
  wird verworfen statt einen Fehler zu werfen. Eine explizite
  Registrierung derselben Klasse hat Vorrang.
- Rohe Skalare (API-Keys, Hosts) lassen sich nicht autowiren. Entweder in
  kleine Value-Objects verpacken und per `instance()` registrieren, oder
  `factory()` verwenden.

## Env-Helper

`Wundii\Flowcrafter\Env` liest Umgebungsvariablen typisiert und fällt auf
den Default zurück, wenn die Variable fehlt **oder** leer ist:

```php
Env::string('API_KEY', 'fallback');
Env::int('API_TIMEOUT', 30);
Env::bool('FEATURE_X', false);
```
