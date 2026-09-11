# Testing and release checklist

## Local checks

```bash
composer install
composer check
composer cs-check
```

The coroutine test deliberately interleaves 20 Swoole coroutines and verifies
that each one sees only its own OpenTelemetry context.

## Integration test application

Before a release, exercise a Webman endpoint which performs one PDO query, one
Redis command, one custom span and one log entry. Under concurrent k6 traffic,
verify all of the following:

1. Every sampled request has exactly one Webman server span.
2. PDO and Redis spans have that server span or a custom business span as parent.
3. `trace_id` in Loki opens the same Tempo trace.
4. No span from one request appears below another request.
5. Error responses contain exception events without request payloads or secrets.
6. Worker memory stabilizes during a long soak test.

Recommended scenarios:

- smoke: 1 VU for 30 seconds;
- concurrency: 50 VUs with forced DB/Redis waits;
- soak: normal expected traffic for at least 30 minutes;
- sampling: verify parent-based behavior with sampled and unsampled traceparents.

## Performance comparison

Record at least three runs for each configuration:

- instrumentation disabled;
- server spans only;
- server + PDO + Redis spans;
- production sampling ratio.

Compare request rate, p95/p99 latency, CPU and exported spans. Never enable
method-level spans globally merely to improve trace detail.
