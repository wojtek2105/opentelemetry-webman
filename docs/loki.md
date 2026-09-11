# Loki log correlation

The package contains a processor compatible with Monolog 2 and 3. It adds the
currently active `trace_id`, `span_id` and `trace_flags` to `extra`.

## Webman configuration

Add the processor to every channel that should be correlated:

```php
use Wojtek2105\OpenTelemetryWebman\Logging\TraceContextProcessor;

return [
    'default' => [
        'handlers' => [
            // Keep the existing JSON stdout handler.
        ],
        'processors' => [
            ['class' => TraceContextProcessor::class],
        ],
    ],
];
```

With `Monolog\Formatter\JsonFormatter`, a correlated record contains:

```json
{
  "message": "Customer loaded",
  "extra": {
    "trace_id": "4c6bdc...",
    "span_id": "bc91e2...",
    "trace_flags": "01"
  }
}
```

Alloy can continue forwarding stdout to Loki; the application does not need to
send logs directly over the network.

## Loki query

Nested JSON fields are normally flattened by LogQL's JSON parser:

```logql
{service_name="php-app"} | json | extra_trace_id="4c6bdc..."
```

Configure Tempo's **Trace to logs** query to match the `trace_id`. Configure a
Loki derived field for `extra.trace_id` to navigate in the other direction.
Exact provisioning keys vary by Grafana version, so keep that configuration in
the application's observability stack rather than this framework package.

## Logs outside a request

Worker startup, timers and background processes may not have an active span.
The processor leaves such records unchanged. Wrap important jobs in
`Telemetry::span()` if they should start or join a trace.
