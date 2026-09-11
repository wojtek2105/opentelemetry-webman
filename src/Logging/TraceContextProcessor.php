<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman\Logging;

use Monolog\LogRecord;
use OpenTelemetry\API\Trace\Span;

/**
 * Adds OpenTelemetry identifiers to Monolog 2 and Monolog 3 records.
 *
 * The class intentionally does not implement Monolog's version-specific
 * ProcessorInterface, so one package can support both record representations.
 */
final class TraceContextProcessor
{
    public function __invoke(mixed $record): mixed
    {
        $context = Span::getCurrent()->getContext();
        if (!$context->isValid()) {
            return $record;
        }

        $fields = [
            'trace_id' => $context->getTraceId(),
            'span_id' => $context->getSpanId(),
            'trace_flags' => sprintf('%02x', $context->getTraceFlags()),
        ];

        // Monolog 2 uses arrays.
        if (is_array($record)) {
            $extra = $record['extra'] ?? [];
            $record['extra'] = array_merge(is_array($extra) ? $extra : [], $fields);

            return $record;
        }

        // Monolog 3 uses immutable LogRecord objects with with().
        if ($record instanceof LogRecord) {
            return $record->with(extra: array_merge($record->extra, $fields));
        }

        return $record;
    }
}
