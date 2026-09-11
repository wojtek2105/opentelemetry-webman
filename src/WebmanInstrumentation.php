<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;
use OpenTelemetry\Contrib\Context\Swoole\SwooleContextStorage;
use Wojtek2105\OpenTelemetryWebman\Hooks\WebmanRequestHook;

final class WebmanInstrumentation
{
    public const NAME = 'webman';
    public const SCOPE_NAME = 'io.opentelemetry.contrib.php.webman';

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        self::installCoroutineContextStorage();

        WebmanRequestHook::register(
            new CachedInstrumentation(self::SCOPE_NAME),
            InstrumentationConfig::fromEnvironment(),
        );
    }

    private static function installCoroutineContextStorage(): void
    {
        $storage = Context::storage();

        if ($storage instanceof SwooleContextStorage) {
            return;
        }

        // The default OpenTelemetry storage is execution-context aware. Wrapping it
        // makes scope switches follow native Swoole coroutine switches.
        Context::setStorage(new SwooleContextStorage(new ContextStorage()));
    }
}
