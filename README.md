# Canonical Context Logging - Laravel Package

Laravel integration for [Canonical Context Logging](https://github.com/gilbitron/canonical-context-logging) - structured JSON logs with one wide event per request.

## Overview

This package integrates the [Canonical Context Logging PHP SDK](https://github.com/gilbitron/canonical-context-logging) with Laravel, providing:

- **Automatic request logging** via HTTP middleware
- **Laravel service provider** for easy setup
- **Configuration file** for all settings
- **Facade and helper functions** for easy access
- **W3C Trace Context** support for distributed tracing
- **User context** automatically captured from Laravel's auth

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, or 12
- [Canonical Context Logging PHP SDK](https://github.com/gilbitron/canonical-context-logging)

## Installation

```bash
composer require gilbitron/canonical-context-logging-laravel
```

The service provider will be auto-discovered by Laravel.

## Quick Start

### 1. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=canonical-context-logging-config
```

This creates `config/canonical-context-logging.php` with all available options.

### 2. Configure Environment Variables

Add to your `.env`:

```env
# Exporter type: console, file, or otlp
CANONICAL_CONTEXT_EXPORTER_TYPE=console

# For console exporter (development)
CANONICAL_CONTEXT_CONSOLE_STDERR=true
CANONICAL_CONTEXT_CONSOLE_PRETTY=false

# For file exporter
CANONICAL_CONTEXT_FILE_PATH=/var/log/app/canonical.jsonl

# For OTLP exporter (or use standard OTEL env vars)
CANONICAL_CONTEXT_OTLP_ENDPOINT=https://otel-collector:4318
# Or use: OTEL_EXPORTER_OTLP_ENDPOINT=https://otel-collector:4318

# Logger settings
CANONICAL_CONTEXT_SLOW_THRESHOLD=1.0
CANONICAL_CONTEXT_SAMPLE_RATE=0.1

# Service info (defaults to app.name and app.version)
CANONICAL_CONTEXT_SERVICE_NAME=my-service
CANONICAL_CONTEXT_SERVICE_VERSION=1.0.0

# Middleware settings
CANONICAL_CONTEXT_MIDDLEWARE_ENABLED=true
CANONICAL_CONTEXT_CAPTURE_USER=true
CANONICAL_CONTEXT_CAPTURE_HEADERS=false
```

### 3. That's It!

The middleware is automatically registered and will start logging requests. Each request will emit one structured JSON log entry with all context.

## Usage

### Automatic Logging

The middleware automatically captures:
- Request method, path, URL, IP, user agent
- Response status code
- Request duration
- Authenticated user (if enabled)
- Errors/exceptions
- Trace and span IDs (from W3C Trace Context headers)

### Adding Custom Context

Use the Facade:

```php
use CanonicalContextLogging\Laravel\Facades\CanonicalContext;

// Add single context
CanonicalContext::addContext('org_id', 123);
CanonicalContext::addContext('plan', 'premium');
CanonicalContext::addContext('feature_flags', ['feature_a', 'feature_b']);

// Add multiple at once
CanonicalContext::addContexts([
    'org_id' => 123,
    'plan' => 'premium',
    'feature_flags' => ['feature_a', 'feature_b'],
]);

// Set error
try {
    // ...
} catch (\Exception $e) {
    CanonicalContext::setError($e);
    throw $e;
}
```

Or use helper functions:

```php
// Get current context
$context = canonical_context();

// Add context
canonical_add_context('org_id', 123);

// Set error
canonical_set_error($exception);
```

### Accessing Context Directly

```php
use CanonicalContextLogging\Laravel\Facades\CanonicalContext;

$context = CanonicalContext::context();
if ($context !== null) {
    $context->addContext('custom_key', 'custom_value');
}
```

### Integration with Exception Handler

The middleware automatically captures exceptions, but you can also manually set errors in your exception handler:

```php
// app/Exceptions/Handler.php
use CanonicalContextLogging\Laravel\Facades\CanonicalContext;

public function render($request, \Throwable $e)
{
    CanonicalContext::setError($e);
    return parent::render($request, $e);
}
```

## Configuration

### Exporter Types

#### Console Exporter (Development)

Outputs logs to stdout/stderr:

```php
'exporter' => [
    'type' => 'console',
    'console' => [
        'use_stderr' => true,  // Use stderr instead of stdout
        'pretty_print' => false, // Pretty-print JSON
    ],
],
```

#### File Exporter

Writes logs to a file in JSONL format:

```php
'exporter' => [
    'type' => 'file',
    'file' => [
        'path' => storage_path('logs/canonical.jsonl'),
        'pretty_print' => false,
    ],
],
```

#### OTLP Exporter (Production)

Sends logs to an OpenTelemetry collector:

```php
'exporter' => [
    'type' => 'otlp',
    'otlp' => [
        'endpoint' => 'https://otel-collector:4318',
        'protocol' => 'http/protobuf', // or 'http/json'
        'headers' => ['Authorization' => 'Bearer token'],
        'timeout' => 10,
    ],
],
```

Or use standard OpenTelemetry environment variables:

```bash
export OTEL_EXPORTER_OTLP_ENDPOINT=https://otel-collector:4318
export OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
export OTEL_EXPORTER_OTLP_HEADERS="Authorization=Bearer token123"
```

### Logger Settings

```php
'logger' => [
    'slow_request_threshold' => 1.0, // Always log requests slower than 1 second
    'sample_rate' => 0.1, // Sample 10% of normal requests
],
```

**Tail-Sampling Rules:**
- Errors are always logged
- Slow requests (above threshold) are always logged
- Other requests are sampled based on `sample_rate`
- Set `sample_rate` to `null` to log all requests

### Service Configuration

```php
'service' => [
    'name' => 'my-service', // Defaults to config('app.name')
    'version' => '1.0.0',   // Defaults to config('app.version', '1.0.0')
],
```

### Middleware Configuration

```php
'middleware' => [
    'enabled' => true,              // Enable/disable middleware
    'capture_user' => true,         // Capture authenticated user info
    'capture_request_headers' => false, // Capture all request headers
],
```

## Log Output Format

The structured log output follows this schema:

```json
{
  "timestamp": "2024-01-15T10:30:45+00:00",
  "trace_id": "a1b2c3d4e5f6...",
  "span_id": "1234567890abcdef",
  "service": {
    "name": "my-service",
    "version": "1.0.0"
  },
  "status": 200,
  "duration": 0.123,
  "context": {
    "http.method": "GET",
    "http.path": "api/users",
    "http.url": "https://example.com/api/users",
    "http.ip": "192.168.1.1",
    "http.user_agent": "Mozilla/5.0...",
    "user.id": 123,
    "user.email": "user@example.com",
    "org_id": 456,
    "plan": "premium",
    "feature_flags": ["feature_a", "feature_b"]
  },
  "error": {
    "type": "RuntimeException",
    "message": "Something went wrong",
    "file": "/path/to/file.php",
    "line": 42,
    "code": 0
  }
}
```

## W3C Trace Context Support

The middleware automatically extracts trace and span IDs from the `traceparent` header if present:

```
traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
```

This enables distributed tracing across services.

## Disabling Middleware

To disable automatic request logging:

```php
// config/canonical-context-logging.php
'middleware' => [
    'enabled' => false,
],
```

Or set environment variable:

```env
CANONICAL_CONTEXT_MIDDLEWARE_ENABLED=false
```

You can still use the Facade and helpers to manually log events.

## Manual Logging (Without Middleware)

If you disable the middleware, you can manually start and end contexts:

```php
use CanonicalContextLogging\Laravel\Facades\CanonicalContext;
use CanonicalContextLogging\Middleware\RequestMiddleware;

$middleware = app(RequestMiddleware::class);

// Start context
$context = $middleware->start();
$context->setService('my-service', '1.0.0');
$context->addContext('user_id', 123);

// ... your application logic ...

// End context
$context->setStatus(200);
$middleware->end($context);
```

## Testing

The package includes tests. Run them with:

```bash
composer test
```

## Best Practices

1. **One log per request** - The middleware handles this automatically
2. **Add business context** - Use the Facade to add user, org, plan, feature flags, etc.
3. **Structured data only** - No free-text messages, use consistent keys
4. **Tail-sample** - Configure sampling to reduce volume while keeping errors and slow requests
5. **Error handling** - Exceptions are automatically captured, but you can add additional context
6. **W3C Trace Context** - Include traceparent headers in upstream requests for distributed tracing

## License

MIT
