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

Starting before `Context::reset()` would lose the request state. Ending after
`Context::destroy()` would lose the active parent. This ordering is also why a
generic PHP-FPM middleware implementation is insufficient for this runtime.

## Coroutine isolation

`open-telemetry/context-swoole` wraps OpenTelemetry's context storage and follows
native Swoole coroutine switches. Each concurrent request therefore has its own
active root span even though many requests execute inside one long-running PHP
worker.

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
