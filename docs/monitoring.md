# Monitoring (Prometheus / OpenMetrics)
- [Back to README.md](./../README.md)

Der Endpunkt `GET /metrics` gibt Metriken im
[Prometheus-Textformat](https://prometheus.io/docs/instrumenting/exposition_formats/)
(Version 0.0.4) zurück und ist ohne Authentication erreichbar. Die
Absicherung erfolgt auf Netzwerkebene (Firewall, Reverse Proxy).

## Exportierte Metriken

| Metrik                                 | Typ   | Beschreibung                                                      |
|----------------------------------------|-------|-------------------------------------------------------------------|
| `flowcrafter_info`                     | gauge | Immer `1`, Labels `description` und `storage` enthalten Metadaten |
| `flowcrafter_observer_up`              | gauge | `1` = Observer läuft, `0` = Observer gestoppt                     |
| `flowcrafter_observer_workers`         | gauge | Anzahl der aktiven Observer-Worker-Prozesse                       |
| `flowcrafter_scheduler_up`             | gauge | `1` = Scheduler läuft, `0` = Scheduler gestoppt                   |
| `flowcrafter_scheduler_workers`        | gauge | Anzahl der aktiven Scheduler-Prozesse                             |
| `flowcrafter_projection_up`            | gauge | `1` = Projection-Worker läuft, `0` = gestoppt                     |
| `flowcrafter_projection_workers`       | gauge | Anzahl der aktiven Projection-Worker-Prozesse                     |
| `flowcrafter_queue_size`               | gauge | Aktuelle Anzahl der Einträge in der Queue                         |
| `flowcrafter_flows_total`              | gauge | Gesamtanzahl aller Flow-Instanzen                                 |
| `flowcrafter_exceptions_7d`            | gauge | Anzahl der Flow-Exceptions in den letzten 7 Tagen                 |
| `flowcrafter_schedule_exceptions_7d`   | gauge | Anzahl der Schedule-Exceptions in den letzten 7 Tagen             |
| `flowcrafter_projection_exceptions_7d` | gauge | Anzahl der Projection-Exceptions in den letzten 7 Tagen           |

Die `*_up`-/`*_workers`-Metriken leiten sich aus den Heartbeats der
jeweiligen Prozesse ab (Observer, Scheduler, Projection-Worker).

## Beispielausgabe

```
# HELP flowcrafter_info FlowCrafter service information
# TYPE flowcrafter_info gauge
flowcrafter_info{description="Production",storage="Redis"} 1
# HELP flowcrafter_observer_up Whether the FlowCrafter observer process is running (1 = up, 0 = down)
# TYPE flowcrafter_observer_up gauge
flowcrafter_observer_up 1
# HELP flowcrafter_observer_workers Number of active observer worker processes
# TYPE flowcrafter_observer_workers gauge
flowcrafter_observer_workers 2
# HELP flowcrafter_scheduler_up Whether the FlowCrafter scheduler process is running (1 = up, 0 = down)
# TYPE flowcrafter_scheduler_up gauge
flowcrafter_scheduler_up 1
# HELP flowcrafter_scheduler_workers Number of active scheduler instances
# TYPE flowcrafter_scheduler_workers gauge
flowcrafter_scheduler_workers 1
# HELP flowcrafter_projection_up Whether the FlowCrafter projection worker is running (1 = up, 0 = down)
# TYPE flowcrafter_projection_up gauge
flowcrafter_projection_up 1
# HELP flowcrafter_projection_workers Number of active projection worker processes
# TYPE flowcrafter_projection_workers gauge
flowcrafter_projection_workers 1
# HELP flowcrafter_queue_size Number of items currently pending in the queue
# TYPE flowcrafter_queue_size gauge
flowcrafter_queue_size 0
# HELP flowcrafter_flows_total Total number of flow instances
# TYPE flowcrafter_flows_total gauge
flowcrafter_flows_total 42
# HELP flowcrafter_exceptions_7d Number of exceptions in the last 7 days
# TYPE flowcrafter_exceptions_7d gauge
flowcrafter_exceptions_7d 3
# HELP flowcrafter_schedule_exceptions_7d Number of schedule exceptions in the last 7 days
# TYPE flowcrafter_schedule_exceptions_7d gauge
flowcrafter_schedule_exceptions_7d 0
# HELP flowcrafter_projection_exceptions_7d Number of projection exceptions in the last 7 days
# TYPE flowcrafter_projection_exceptions_7d gauge
flowcrafter_projection_exceptions_7d 1
```

## Prometheus-Konfiguration

```yaml
scrape_configs:
  - job_name: flowcrafter
    static_configs:
      - targets: ['localhost:8000']
    metrics_path: /metrics
```

## CheckMK

In CheckMK den **Prometheus Special Agent** oder einen **HTTP-Check** auf
`/metrics` einrichten. Das Format wird nativ als Prometheus-Exposition
erkannt.
