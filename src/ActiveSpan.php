<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

final class ActiveSpan
{
    private bool $ended = false;

    public function __construct(
        private readonly SpanInterface $span,
        private readonly object $scope,
    ) {}

    public function span(): SpanInterface
    {
        return $this->span;
    }

    /**
     * @param non-empty-string $name
     * @param array<array-key, bool|float|int|string>|bool|float|int|string|null $value
     */
    public function setAttribute(string $name, array|bool|float|int|string|null $value): self
    {
        $this->span->setAttribute($name, $value);

        return $this;
    }

    /** @param array<string, mixed> $attributes */
    public function addEvent(string $name, array $attributes = []): self
    {
        $this->span->addEvent($name, $attributes);

        return $this;
    }

    public function recordException(Throwable $exception): self
    {
        $this->span->recordException($exception);
        $this->span->setAttribute('error.type', $exception::class);
        $this->span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());

        return $this;
    }

    public function end(?Throwable $exception = null): void
    {
        if ($this->ended) {
            return;
        }

        $this->ended = true;
        if ($exception !== null) {
            $this->recordException($exception);
        }

        try {
            if (method_exists($this->scope, 'detach')) {
                $this->scope->detach();
            }
        } finally {
            $this->span->end();
        }
    }
}
