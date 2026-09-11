<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman\Tests;

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
}
