<?php

declare(strict_types=1);

namespace Wojtek2105\OpenTelemetryWebman\Tests;

use PHPUnit\Framework\TestCase;
use Wojtek2105\OpenTelemetryWebman\Logging\TraceContextProcessor;

final class TraceContextProcessorTest extends TestCase
{
    public function testRecordWithoutActiveSpanIsUnchanged(): void
    {
        $record = ['message' => 'worker started', 'extra' => ['worker' => 1]];

        self::assertSame($record, (new TraceContextProcessor())($record));
    }
}
