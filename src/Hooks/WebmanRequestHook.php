<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman\Hooks;

use ArrayObject;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

use function OpenTelemetry\Instrumentation\hook;

use Throwable;
use Webman\App;
use Webman\Context as WebmanContext;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Route\Route;
use Wojtek2105\OpenTelemetryWebman\InstrumentationConfig;
use Wojtek2105\OpenTelemetryWebman\RequestTraceState;
use Workerman\Coroutine\Context as WorkermanContext;

final class WebmanRequestHook
{
    public static function register(CachedInstrumentation $instrumentation, InstrumentationConfig $config): void
    {
        // Webman resets its coroutine-local context at the beginning of onMessage().
        // Starting the span in this post-hook is deliberate: a pre-hook would be
        // erased by that reset and would leak/cross request contexts under load.
        hook(
            WorkermanContext::class,
            'reset',
            post: static function (string $class, array $params, mixed $returnValue, ?Throwable $exception) use ($instrumentation, $config): void {
                if ($exception !== null || !isset($params[0]) || !$params[0] instanceof ArrayObject) {
                    return;
                }

                $request = $params[0][Request::class] ?? null;
                if (!$request instanceof Request) {
                    return;
                }

                $path = $request->path();
                if ($config->excludes($path)) {
                    return;
                }

                try {
                    self::startRequest($instrumentation, $config, $request, $path);
                } catch (Throwable) {
                    // Instrumentation must never change application behavior.
                }
            },
        );

        // This hook runs immediately before Webman destroys its coroutine context.
        hook(
            App::class,
            'send',
            pre: static function (string $class, array $params): void {
                try {
                    self::finishRequest($params[1] ?? null, $params[2] ?? null);
                } catch (Throwable) {
                    // Instrumentation must never prevent Webman from responding.
                }
            },
        );
    }

    private static function startRequest(
        CachedInstrumentation $instrumentation,
        InstrumentationConfig $config,
        Request $request,
        string $path,
    ): void {
        $parent = Globals::propagator()->extract($request->header());
        $method = strtoupper($request->method());

        $span = $instrumentation->tracer()
            ->spanBuilder('HTTP ' . $method)
            ->setParent($parent)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        if ($span->isRecording()) {
            $span->setAttribute('http.request.method', $method);
            $span->setAttribute('url.path', $path);
            $span->setAttribute('network.protocol.version', $request->protocolVersion());
            $span->setAttribute('server.address', $request->host(true));
            $span->setAttribute('server.port', $request->getLocalPort());

            $userAgent = $request->header('user-agent');
            if (is_string($userAgent)) {
                $span->setAttribute('user_agent.original', $userAgent);
            }

            if ($config->captureClientAddress) {
                $span->setAttribute('client.address', $request->getRemoteIp());
                $span->setAttribute('client.port', $request->getRemotePort());
            }

            if ($config->captureUrlQuery) {
                $span->setAttribute('url.full', $request->fullUrl());
            }
        }

        $scope = $span->activate();
        WebmanContext::set(RequestTraceState::class, new RequestTraceState($span, $scope));
    }

    private static function finishRequest(mixed $response, mixed $request): void
    {
        $state = WebmanContext::get(RequestTraceState::class);
        if (!$state instanceof RequestTraceState) {
            return;
        }

        try {
            if (!$state->span->isRecording()) {
                return;
            }

            if ($request instanceof Request) {
                self::addRouteAttributes($state, $request);
            }

            if ($response instanceof Response) {
                self::addResponseAttributes($state, $response);
            }
        } finally {
            $state->end();
        }
    }

    private static function addRouteAttributes(RequestTraceState $state, Request $request): void
    {
        $method = strtoupper($request->method());
        $route = $request->route;

        if ($route instanceof Route) {
            $routePath = $route->getPath();
            $state->span->updateName($method . ' ' . $routePath);
            $state->span->setAttribute('http.route', $routePath);

            if ($route->getName() !== null) {
                $state->span->setAttribute('webman.route.name', $route->getName());
            }

            return;
        }

        $path = '/' . ltrim($request->path(), '/');
        $state->span->updateName($method . ' ' . $path);
    }

    private static function addResponseAttributes(RequestTraceState $state, Response $response): void
    {
        $status = $response->getStatusCode();
        $state->span->setAttribute('http.response.status_code', $status);

        $contentLength = $response->getHeader('content-length');
        if (is_numeric($contentLength)) {
            $state->span->setAttribute('http.response.body.size', (int) $contentLength);
        }

        $exception = $response->exception();
        if ($exception instanceof Throwable) {
            $state->span->recordException($exception);
            $state->span->setAttribute('error.type', $exception::class);
            $state->span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());

            return;
        }

        if ($status >= 500) {
            $state->span->setStatus(StatusCode::STATUS_ERROR);
        }
    }
}
