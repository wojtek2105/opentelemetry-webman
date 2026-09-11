# Pyroscope integration

Pyroscope and tracing answer different questions:

- OpenTelemetry traces show request causality and dependency latency.
- Pyroscope shows sampled call stacks and CPU usage over time.

## Current eBPF limitation

Grafana's eBPF profiler can profile PHP without modifying application code, but
it cannot currently correlate application-created OpenTelemetry spans per span.
Do not add a fake `pyroscope.profile.id` attribute: Grafana would display a link
whose query has no matching profile samples.

The package therefore does not pretend to provide PHP span profiles. Keep these
identifiers aligned instead:

```dotenv
OTEL_SERVICE_NAME=php-app
```

```alloy
discovery.relabel "php" {
  rule {
    target_label = "service_name"
    replacement  = "php-app"
  }
}
```

Grafana can then open the aggregate PHP profile for the service and the time
window around a trace. This is useful, but it is not a profile isolated to one
request.

## True span profiles

True span profiles require a PHP profiling SDK/bridge which tags every profile
sample with the active `trace_id` and `span_id`, plus
`pyroscope.profile.id=<span-id>` on the corresponding span. Grafana currently
documents client bridges for selected languages but not PHP.

When an official PHP bridge becomes available, it can consume the active
context created by this package without changing application code. Until then:

- use traces for DB, Redis, HTTP and business-operation timings;
- use eBPF Pyroscope for complete function stacks and CPU hotspots;
- correlate them approximately by `service.name`, process and time range;
- use `trace_id` in Loki logs to move reliably between logs and traces.

Short requests may not produce a useful profile at a low sampling rate. A
profile is statistical; a trace is an exact timing record for a sampled request.
