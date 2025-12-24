<?php

declare(strict_types=1);

namespace CanonicalContextLogging\Laravel\Middleware;

use CanonicalContextLogging\Middleware\RequestMiddlewareInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel HTTP middleware for Canonical Context Logging.
 * Automatically captures request/response data and user context.
 */
final class CanonicalContextMiddleware
{
    public function __construct(
        private readonly RequestMiddlewareInterface $requestMiddleware
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('canonical-context-logging');

        // Extract trace/span IDs from W3C Trace Context headers if present
        $traceId = $this->extractTraceId($request);
        $spanId = $this->extractSpanId($request);

        // Start context
        $context = $this->requestMiddleware->start($traceId, $spanId);

        // Set service info
        $serviceName = $config['service']['name'] ?? config('app.name', 'laravel');
        $serviceVersion = $config['service']['version'] ?? config('app.version', '1.0.0');
        $context->setService($serviceName, $serviceVersion);

        // Capture request metadata
        $context->addContext('http.method', $request->method());
        $context->addContext('http.path', $request->path());
        $context->addContext('http.url', $request->fullUrl());
        $context->addContext('http.ip', $request->ip());
        $context->addContext('http.user_agent', $request->userAgent());

        // Capture authenticated user if enabled
        if (($config['middleware']['capture_user'] ?? true) && Auth::check()) {
            $user = Auth::user();
            $context->addContext('user.id', $user->getAuthIdentifier());
            $context->addContext('user.email', $user->email ?? null);
        }

        // Capture request headers if enabled
        if ($config['middleware']['capture_request_headers'] ?? false) {
            $headers = [];
            foreach ($request->headers->all() as $key => $values) {
                $headers[strtolower($key)] = count($values) === 1 ? $values[0] : $values;
            }
            $context->addContext('http.headers', $headers);
        }

        try {
            $response = $next($request);

            // Set response status
            $context->setStatus($response->getStatusCode());

            return $response;
        } catch (\Throwable $e) {
            // Capture exception
            $context->setError($e);
            $context->setStatus(500);

            // Re-throw to let Laravel's exception handler deal with it
            throw $e;
        } finally {
            // Always end context, even if exception occurred
            $this->requestMiddleware->end($context);
        }
    }

    /**
     * Extract trace ID from W3C Trace Context header.
     */
    private function extractTraceId(Request $request): ?string
    {
        $traceParent = $request->header('traceparent');
        if ($traceParent === null) {
            return null;
        }

        // W3C Trace Context format: version-trace_id-span_id-trace_flags
        // Example: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
        $parts = explode('-', $traceParent);
        if (count($parts) >= 2 && strlen($parts[1]) === 32) {
            return $parts[1];
        }

        return null;
    }

    /**
     * Extract span ID from W3C Trace Context header.
     */
    private function extractSpanId(Request $request): ?string
    {
        $traceParent = $request->header('traceparent');
        if ($traceParent === null) {
            return null;
        }

        // W3C Trace Context format: version-trace_id-span_id-trace_flags
        $parts = explode('-', $traceParent);
        if (count($parts) >= 3 && strlen($parts[2]) === 16) {
            return $parts[2];
        }

        return null;
    }
}
