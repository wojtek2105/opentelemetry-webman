# OpenTelemetry for Webman

Coroutine-safe OpenTelemetry instrumentation and a small tracing SDK for
Webman 2, Workerman and Swoole.

The package creates one server span for every dynamic Webman request, preserves
W3C `traceparent`, keeps active contexts isolated between Swoole coroutines and
adds helpers for custom child spans, events and attributes.

## What appears in a trace

With the optional PDO and Redis instrumentations installed, a trace can look
like this:

```text
GET /customers/{id}
├── customer.load
│   ├── SELECT app.customers
│   └── GET customer:42
└── crm.calculate-offer
```

The package does **not** trace every PHP function automatically. Doing so would
create excessive overhead and unusably large traces. Instrument important
service boundaries with `Telemetry::span()` or OpenTelemetry's `#[WithSpan]`.
Use Pyroscope for an exhaustive sampled view of executed PHP functions.

## Install

```bash
composer require wojtek2105/opentelemetry-webman
composer require open-telemetry/opentelemetry-auto-pdo
composer require mismatch/opentelemetry-auto-redis
```

The `opentelemetry`, `swoole` and preferably `protobuf` PHP extensions must be
enabled. See [installation](docs/installation.md) for Docker and OTLP setup.

## Custom spans

```php
use Wojtek2105\OpenTelemetryWebman\Telemetry;

$customer = Telemetry::span(
    'customer.load',
    fn () => Customer::findOrFail($id),
    ['customer.id' => $id],
);
```

The callback result is returned unchanged. Exceptions are recorded and rethrown,
and the scope is always closed. See the complete [custom SDK guide](docs/custom-spans.md).

## Documentation

- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Custom spans SDK](docs/custom-spans.md)
- [Loki log correlation](docs/loki.md)
- [Pyroscope integration](docs/pyroscope.md)
- [Architecture and lifecycle](docs/architecture.md)
- [Testing and release checklist](docs/testing.md)
- [Publishing on GitHub and Packagist](docs/publishing.md)

## Quality checks

```bash
composer check
composer cs-check
```

## License

GPL-3.0-only
