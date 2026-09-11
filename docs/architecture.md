# Architecture

## Request lifecycle

Webman begins `App::onMessage()` by resetting `Workerman\Coroutine\Context` and
ends `App::send()` by destroying it. Those two operations define the safe
request-context lifetime.

The instrumentation registers two low-level observer hooks:

```text
Workerman Context::reset() returns
└── extract incoming traceparent
    └── start and activate Webman SERVER span
        └── router, middleware, controller
            ├── custom application spans
            ├── PDO spans
            ├── Redis spans
            └── outgoing HTTP spans

Webman App::send() begins
└── add route, response and exception attributes
    └── detach scope and end SERVER span
        └── Webman destroys coroutine context
```

PDO, Redis and outgoing HTTP instrumentations do not need a special "child
span" API. Every OpenTelemetry span becomes a child when it is started while
the Webman server span (or a custom business span) is active. The single-trace
waterfall in Tempo is authoritative for this relationship.

Grafana's **Service structure** search view aggregates multiple traces by their
shape. A standalone PDO or Redis structure means that the operation ran without
an active HTTP request, for example in a CLI command, readiness check, timer or
background worker. It does not detach dependency spans in request traces.

Starting before `Context::reset()` would lose the request state. Ending after
`Context::destroy()` would lose the active parent. This ordering is also why a
generic PHP-FPM middleware implementation is insufficient for this runtime.

## Coroutine isolation

`open-telemetry/context-swoole` wraps OpenTelemetry's context storage and follows
native Swoole coroutine switches. Each concurrent request therefore has its own
active root span even though many requests execute inside one long-running PHP
worker.

## Hot path

When `OTEL_SDK_DISABLED=true`, the package does not install observer hooks. For
an unsampled request it still creates and activates a non-recording span so that
trace context can be propagated correctly, but skips request, route and response
attribute collection. Tracers are cached through OpenTelemetry's
`CachedInstrumentation` helper.

## Failure behavior

- A request state can be ended only once.
- Custom callback spans close in `finally`.
- Exceptions are recorded and then rethrown unchanged.
- Excluded routes do not create an active server span.
- Instrumentation should never contain application payloads by default.

## Signal ownership

| Signal | Owner |
| --- | --- |
| Request/dependency traces | This package + OpenTelemetry instrumentations |
| Application logs | Monolog, enriched by `TraceContextProcessor` |
| Log storage/search | Alloy and Loki |
| Full sampled PHP stacks | Alloy eBPF profiler and Pyroscope |
| Metrics and dashboards | OpenTelemetry Collector/Grafana stack |
