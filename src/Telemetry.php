<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman;

use InvalidArgumentException;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use Throwable;

final class Telemetry
{
    public const SCOPE_NAME = 'app.webman';

    /**
     * Execute a callback inside a child span and always close its scope.
     *
     * @template T
     * @param non-empty-string $name
     * @param callable(): T $callback
     * @param array<non-empty-string, array<array-key, bool|float|int|string>|bool|float|int|string|null> $attributes
     * @param SpanKind::KIND_INTERNAL|SpanKind::KIND_SERVER|SpanKind::KIND_CLIENT|SpanKind::KIND_PRODUCER|SpanKind::KIND_CONSUMER $kind
     * @return T
     */
    public static function span(
        string $name,
        callable $callback,
        array $attributes = [],
        int $kind = SpanKind::KIND_INTERNAL,
    ): mixed {
        $activeSpan = self::startSpan($name, $attributes, $kind);

        try {
            return $callback();
        } catch (Throwable $exception) {
            $activeSpan->recordException($exception);
            throw $exception;
        } finally {
            $activeSpan->end();
        }
    }

    /**
     * @param non-empty-string $name
     * @param array<non-empty-string, array<array-key, bool|float|int|string>|bool|float|int|string|null> $attributes
     * @param SpanKind::KIND_INTERNAL|SpanKind::KIND_SERVER|SpanKind::KIND_CLIENT|SpanKind::KIND_PRODUCER|SpanKind::KIND_CONSUMER $kind
     */
    public static function startSpan(
        string $name,
        array $attributes = [],
        int $kind = SpanKind::KIND_INTERNAL,
    ): ActiveSpan {
        if ($name === '') {
            throw new InvalidArgumentException('A span name must not be empty.');
        }

        $builder = Globals::tracerProvider()
            ->getTracer(self::SCOPE_NAME)
            ->spanBuilder($name)
            ->setSpanKind($kind);

        foreach ($attributes as $attribute => $value) {
            $builder->setAttribute($attribute, $value);
        }

        $span = $builder->startSpan();

        return new ActiveSpan($span, $span->activate());
    }

    public static function currentSpan(): SpanInterface
    {
        return Span::getCurrent();
    }

    /**
     * @param non-empty-string $name
     * @param array<array-key, bool|float|int|string>|bool|float|int|string|null $value
     */
    public static function setAttribute(string $name, array|bool|float|int|string|null $value): void
    {
        self::currentSpan()->setAttribute($name, $value);
    }

    /** @param array<string, mixed> $attributes */
    public static function addEvent(string $name, array $attributes = []): void
    {
        self::currentSpan()->addEvent($name, $attributes);
    }

    public static function recordException(Throwable $exception): void
    {
        self::currentSpan()->recordException($exception);
    }

    public static function traceId(): ?string
    {
        $context = self::currentSpan()->getContext();

        return $context->isValid() ? $context->getTraceId() : null;
    }

    public static function spanId(): ?string
    {
        $context = self::currentSpan()->getContext();

        return $context->isValid() ? $context->getSpanId() : null;
    }
}
