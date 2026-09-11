<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman\Tests;

use OpenTelemetry\SDK\SdkBuilder;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wojtek2105\OpenTelemetryWebman\Telemetry;

final class TelemetryTest extends TestCase
{
    public function testSpanReturnsCallbackResultWithNoopProvider(): void
    {
        self::assertSame('result', Telemetry::span('test.operation', static fn(): string => 'result'));
    }

    public function testSpanRethrowsApplicationException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        Telemetry::span('test.failure', static function (): never {
            throw new RuntimeException('boom');
        });
    }

    public function testNestedSpanUsesActiveSpanAsParent(): void
    {
        $exporter = new InMemoryExporter();
        $provider = new TracerProvider(new SimpleSpanProcessor($exporter));
        $scope = (new SdkBuilder())
            ->setTracerProvider($provider)
            ->buildAndRegisterGlobal();

        try {
            Telemetry::span('parent', static function (): void {
                Telemetry::span('child', static fn(): null => null);
            });
        } finally {
            $scope->detach();
            $provider->shutdown();
        }

        /** @var list<SpanDataInterface> $exportedSpans */
        $exportedSpans = $exporter->getSpans();
        /** @var array<string, SpanDataInterface> $spans */
        $spans = [];
        foreach ($exportedSpans as $span) {
            $spans[$span->getName()] = $span;
        }

        self::assertSame($spans['parent']->getTraceId(), $spans['child']->getTraceId());
        self::assertSame($spans['parent']->getSpanId(), $spans['child']->getParentSpanId());
    }
}
