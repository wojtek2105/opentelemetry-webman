<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use Wojtek2105\OpenTelemetryWebman\WebmanInstrumentation;

if (!extension_loaded('opentelemetry')) {
    return;
}

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(WebmanInstrumentation::NAME)) {
    return;
}

WebmanInstrumentation::register();
