<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman\Tests;

use PHPUnit\Framework\TestCase;
use Wojtek2105\OpenTelemetryWebman\InstrumentationConfig;

final class InstrumentationConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('OTEL_WEBMAN_EXCLUDED_PATHS');
        putenv('OTEL_WEBMAN_CAPTURE_URL_QUERY');
        putenv('OTEL_WEBMAN_CAPTURE_CLIENT_ADDRESS');
    }

    public function testDefaultsExcludeHealthAndMetrics(): void
    {
        $config = InstrumentationConfig::fromEnvironment();

        self::assertTrue($config->excludes('/health'));
        self::assertTrue($config->excludes('/metrics'));
        self::assertFalse($config->excludes('/customers'));
        self::assertFalse($config->captureUrlQuery);
        self::assertTrue($config->captureClientAddress);
    }

    public function testPrefixExclusionsAndPrivacyOptions(): void
    {
        putenv('OTEL_WEBMAN_EXCLUDED_PATHS=/ready,/internal/*');
        putenv('OTEL_WEBMAN_CAPTURE_URL_QUERY=true');
        putenv('OTEL_WEBMAN_CAPTURE_CLIENT_ADDRESS=false');

        $config = InstrumentationConfig::fromEnvironment();

        self::assertTrue($config->excludes('/ready'));
        self::assertTrue($config->excludes('/internal/jobs'));
        self::assertFalse($config->excludes('/internality'));
        self::assertTrue($config->captureUrlQuery);
        self::assertFalse($config->captureClientAddress);
    }
}
