<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman\Tests;

use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;
use OpenTelemetry\Contrib\Context\Swoole\SwooleContextStorage;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;

final class SwooleContextIsolationTest extends TestCase
{
    public function testConcurrentCoroutinesKeepIndependentContexts(): void
    {
        Context::setStorage(new SwooleContextStorage(new ContextStorage()));
        $key = Context::createKey('request-id');
        $seen = [];

        Coroutine\run(static function () use ($key, &$seen): void {
            for ($request = 1; $request <= 20; ++$request) {
                Coroutine::create(static function () use ($key, $request, &$seen): void {
                    $scope = Context::getCurrent()->with($key, $request)->activate();

                    try {
                        Coroutine::sleep(0.001 * (($request % 3) + 1));
                        $seen[$request] = Context::getCurrent()->get($key);
                    } finally {
                        $scope->detach();
                    }
                });
            }
        });

        ksort($seen);
        self::assertSame(range(1, 20), array_values($seen));
    }
}
