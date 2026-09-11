# Installation

## Requirements

- PHP 8.2 or newer
- Webman 2.1 or newer
- Workerman with the Swoole event loop
- `ext-opentelemetry`
- `ext-swoole`
- an OTLP-compatible collector such as Grafana Alloy or the LGTM image

For production, install `ext-protobuf`; the native encoder substantially reduces
CPU usage compared with the PHP protobuf implementation.

## Composer

Install the package after publishing it on GitHub or Packagist:

```bash
composer require wojtek2105/opentelemetry-webman
```

Install only the dependency instrumentations used by the application:

```bash
composer require open-telemetry/opentelemetry-auto-pdo
composer require mismatch/opentelemetry-auto-redis
composer require open-telemetry/opentelemetry-auto-curl
```

PDO produces database client spans. The Redis package supports PhpRedis and
Predis. Curl instrumentation creates outbound HTTP spans and injects
`traceparent` into downstream requests.

## PHP extensions in Docker

With `install-php-extensions`:

```dockerfile
RUN install-php-extensions opentelemetry protobuf
```

Verify the runtime, not only the build stage:

```bash
php --ri opentelemetry
php --ri protobuf
```

## OTLP environment

```dotenv
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=php-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_ENDPOINT=http://lgtm:4318
OTEL_PROPAGATORS=tracecontext,baggage
OTEL_TRACES_SAMPLER=parentbased_traceidratio
OTEL_TRACES_SAMPLER_ARG=1.0
```

`1.0` is convenient in development. Use a lower value in production, for
example `0.1`, unless tail sampling is performed in the collector.

Use the batch processor so OTLP network I/O does not block every request:

```dotenv
OTEL_PHP_TRACES_PROCESSOR=batch
```

With very low traffic a batch can appear in Tempo after another request or when
the worker shuts down. The `simple` processor is useful only for debugging
export correctness because it synchronously exports every ended span and has a
much larger latency cost. Always restart all Workerman workers after changing
instrumentation or OTel environment variables.

## Avoid duplicate eBPF spans

When application-level tracing is enabled, disable Beyla HTTP, SQL and Redis
instrumentation for the same PHP process. Otherwise Tempo can contain duplicate
or unrelated protocol spans. Beyla can remain enabled for services which do not
have an OpenTelemetry SDK.
