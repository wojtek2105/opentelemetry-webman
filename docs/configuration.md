# Configuration

The standard OpenTelemetry PHP variables configure the SDK, exporter, resource,
propagators and sampling. This package adds only Webman-specific switches.

| Variable | Default | Description |
| --- | --- | --- |
| `OTEL_WEBMAN_EXCLUDED_PATHS` | `/health,/metrics` | Comma-separated exact paths; a trailing `*` matches a prefix. |
| `OTEL_WEBMAN_CAPTURE_URL_QUERY` | `false` | Adds `url.full`. Queries may contain secrets or personal data. |
| `OTEL_WEBMAN_CAPTURE_CLIENT_ADDRESS` | `true` | Adds client IP and port to the server span. |

Examples:

```dotenv
OTEL_WEBMAN_EXCLUDED_PATHS=/health,/metrics,/internal/*
OTEL_WEBMAN_CAPTURE_URL_QUERY=false
OTEL_WEBMAN_CAPTURE_CLIENT_ADDRESS=false
```

Disable this package through the standard instrumentation list:

```dotenv
OTEL_PHP_DISABLED_INSTRUMENTATIONS=webman
```

To disable multiple instrumentations:

```dotenv
OTEL_PHP_DISABLED_INSTRUMENTATIONS=webman,pdo,redis
```

## Privacy and cardinality

- Do not put email addresses, tokens, complete payloads or unrestricted SQL
  parameters into span attributes.
- Prefer route patterns such as `/customers/{id}` over concrete URLs.
- Attribute values should have a bounded set of possible values.
- Record identifiers only when they are genuinely required during development.
