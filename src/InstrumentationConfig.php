<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman;

final readonly class InstrumentationConfig
{
    /** @param list<string> $excludedPaths */
    public function __construct(
        public array $excludedPaths,
        public bool $captureUrlQuery,
        public bool $captureClientAddress,
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            self::csv(self::env('OTEL_WEBMAN_EXCLUDED_PATHS', '/health,/metrics')),
            self::bool(self::env('OTEL_WEBMAN_CAPTURE_URL_QUERY', 'false')),
            self::bool(self::env('OTEL_WEBMAN_CAPTURE_CLIENT_ADDRESS', 'true')),
        );
    }

    public function excludes(string $path): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            if ($excludedPath === $path) {
                return true;
            }

            if (str_ends_with($excludedPath, '*') && str_starts_with($path, substr($excludedPath, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false ? $default : $value;
    }

    /** @return list<string> */
    private static function csv(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $item): bool => $item !== ''));
    }

    private static function bool(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
