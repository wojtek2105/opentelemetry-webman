<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman;

use OpenTelemetry\API\Trace\SpanInterface;

final class RequestTraceState
{
    private bool $ended = false;

    public function __construct(
        public readonly SpanInterface $span,
        private readonly object $scope,
    ) {}

    public function end(): void
    {
        if ($this->ended) {
            return;
        }

        $this->ended = true;

        try {
            if (method_exists($this->scope, 'detach')) {
                $this->scope->detach();
            }
        } finally {
            $this->span->end();
        }
    }
}
