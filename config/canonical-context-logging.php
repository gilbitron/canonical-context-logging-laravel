<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Exporter Configuration
    |--------------------------------------------------------------------------
    |
    | Choose the exporter type: 'console', 'file', or 'otlp'
    |
    */

    'exporter' => [
        'type' => env('CANONICAL_CONTEXT_EXPORTER_TYPE', 'file'),

        'console' => [
            'use_stderr' => env('CANONICAL_CONTEXT_CONSOLE_STDERR', true),
            'pretty_print' => env('CANONICAL_CONTEXT_CONSOLE_PRETTY', false),
        ],

        'file' => [
            'path' => env('CANONICAL_CONTEXT_FILE_PATH', storage_path('logs/canonical.jsonl')),
            'pretty_print' => env('CANONICAL_CONTEXT_FILE_PRETTY', false),
        ],

        'otlp' => [
            // If these are null, the exporter will use OpenTelemetry environment variables:
            // OTEL_EXPORTER_OTLP_ENDPOINT, OTEL_EXPORTER_OTLP_PROTOCOL, etc.
            'endpoint' => env('CANONICAL_CONTEXT_OTLP_ENDPOINT', null),
            'protocol' => env('CANONICAL_CONTEXT_OTLP_PROTOCOL', null), // 'http/protobuf' or 'http/json'
            'headers' => env('CANONICAL_CONTEXT_OTLP_HEADERS', []), // Array of headers
            'timeout' => env('CANONICAL_CONTEXT_OTLP_TIMEOUT', null), // Seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logger Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tail-sampling behavior:
    | - slow_request_threshold: Always log requests slower than this (seconds)
    | - sample_rate: Sample rate for normal requests (0.0 to 1.0)
    |
    | Errors are always logged regardless of these settings.
    |
    */

    'logger' => [
        'slow_request_threshold' => env('CANONICAL_CONTEXT_SLOW_THRESHOLD', 1.0),
        'sample_rate' => env('CANONICAL_CONTEXT_SAMPLE_RATE', 0.1), // 10% sampling
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Configuration
    |--------------------------------------------------------------------------
    |
    | Service metadata included in every log entry.
    | Defaults to Laravel app name and version if not specified.
    |
    */

    'service' => [
        'name' => env('CANONICAL_CONTEXT_SERVICE_NAME', null), // Defaults to config('app.name')
        'version' => env('CANONICAL_CONTEXT_SERVICE_VERSION', null), // Defaults to config('app.version', '1.0.0')
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the HTTP middleware behavior.
    |
    */

    'middleware' => [
        'enabled' => env('CANONICAL_CONTEXT_MIDDLEWARE_ENABLED', true),
        'capture_user' => env('CANONICAL_CONTEXT_CAPTURE_USER', true),
        'capture_request_headers' => env('CANONICAL_CONTEXT_CAPTURE_HEADERS', false),
    ],
];
