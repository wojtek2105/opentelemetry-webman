# Custom spans SDK

## Trace a callback

`Telemetry::span()` is the safest default. It returns the callback result,
records exceptions and always restores the previous active context.

```php
use Wojtek2105\OpenTelemetryWebman\Telemetry;

$result = Telemetry::span('pricing.calculate', function () use ($order) {
    return $this->calculator->calculate($order);
}, [
    'order.type' => $order->type,
]);
```

Every PDO, Redis or outgoing HTTP span created inside the callback becomes its
child automatically.

## A span spanning multiple statements

```php
$span = Telemetry::startSpan('import.batch', ['batch.size' => count($rows)]);

try {
    foreach ($rows as $row) {
        import($row);
    }
    $span->addEvent('batch.persisted');
} catch (Throwable $exception) {
    $span->recordException($exception);
    throw $exception;
} finally {
    $span->end();
}
```

Prefer `Telemetry::span()` where possible. An unclosed manually started scope
can attach later spans to the wrong parent in a long-running worker.

## Enrich the current span

```php
Telemetry::setAttribute('customer.plan', $customer->plan);
Telemetry::addEvent('cache.miss', ['cache.region' => 'customers']);

$traceId = Telemetry::traceId();
$spanId = Telemetry::spanId();
```

## Attribute-based method spans

The OpenTelemetry extension supports selected method instrumentation:

```php
use OpenTelemetry\API\Instrumentation\SpanAttribute;
use OpenTelemetry\API\Instrumentation\WithSpan;

final class OfferService
{
    #[WithSpan('offer.calculate')]
    public function calculate(
        #[SpanAttribute('offer.type')] string $type,
        int $customerId,
    ): Offer {
        // ...
    }
}
```

Only parameters carrying `#[SpanAttribute]` are exported. Never annotate
passwords, tokens, raw request bodies or high-cardinality values.

## Why not trace every method?

Tracing every PHP call changes the workload being measured: it allocates spans,
timestamps and attributes for thousands of short calls and then exports all of
them. Traces are best for causal boundaries and I/O. Pyroscope is the correct
tool for the complete sampled call stack and CPU attribution.
