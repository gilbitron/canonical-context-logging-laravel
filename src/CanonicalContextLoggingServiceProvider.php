<?php

declare(strict_types=1);

namespace CanonicalContextLogging\Laravel;

use CanonicalContextLogging\Context\ContextStorageInterface;
use CanonicalContextLogging\Exporter\ConsoleExporter;
use CanonicalContextLogging\Exporter\ExporterInterface;
use CanonicalContextLogging\Exporter\FileExporter;
use CanonicalContextLogging\Exporter\OtlpConfig;
use CanonicalContextLogging\Exporter\OtlpExporter;
use CanonicalContextLogging\Logger\CanonicalLogger;
use CanonicalContextLogging\Laravel\Context\LaravelStorage;
use CanonicalContextLogging\Laravel\Middleware\CanonicalContextMiddleware;
use CanonicalContextLogging\Middleware\RequestMiddleware;
use CanonicalContextLogging\Middleware\RequestMiddlewareInterface;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Configuration\Middleware as MiddlewareConfiguration;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

final class CanonicalContextLoggingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/canonical-context-logging.php',
            'canonical-context-logging'
        );

        // Register context storage
        // Note: Not a singleton because Request is request-scoped
        $this->app->bind(ContextStorageInterface::class, function ($app) {
            return new LaravelStorage($app->make(Request::class));
        });

        // Register exporter based on config
        $this->app->singleton(ExporterInterface::class, function ($app) {
            $config = $app->make('config');
            $exporterType = $config->get('canonical-context-logging.exporter.type', 'console');

            return match ($exporterType) {
                'console' => new ConsoleExporter(
                    useStderr: $config->get('canonical-context-logging.exporter.console.use_stderr', true),
                    prettyPrint: $config->get('canonical-context-logging.exporter.console.pretty_print', false)
                ),
                'file' => new FileExporter(
                    filePath: $config->get('canonical-context-logging.exporter.file.path', storage_path('logs/canonical.jsonl')),
                    prettyPrint: $config->get('canonical-context-logging.exporter.file.pretty_print', false)
                ),
                'otlp' => $this->createOtlpExporter($config),
                default => throw new \InvalidArgumentException("Unknown exporter type: {$exporterType}")
            };
        });

        // Register logger
        $this->app->singleton(CanonicalLogger::class, function ($app) {
            $config = $app->make('config');
            $slowRequestThreshold = $config->get('canonical-context-logging.logger.slow_request_threshold');
            $sampleRate = $config->get('canonical-context-logging.logger.sample_rate');

            return new CanonicalLogger(
                exporter: $app->make(ExporterInterface::class),
                slowRequestThreshold: $slowRequestThreshold !== null ? (float) $slowRequestThreshold : null,
                sampleRate: $sampleRate !== null ? (float) $sampleRate : null
            );
        });

        // Register request middleware interface binding
        // Note: Not a singleton because it depends on request-scoped storage
        // Using closure to ensure proper type resolution
        $this->app->bind(RequestMiddlewareInterface::class, function ($app): RequestMiddlewareInterface {
            return new RequestMiddleware(
                storage: $app->make(ContextStorageInterface::class),
                logger: $app->make(CanonicalLogger::class)
            );
        });

        // Prevent direct auto-resolution of RequestMiddleware
        // This ensures RequestMiddleware always resolves through the interface
        $this->app->bind(RequestMiddleware::class, function ($app) {
            return $app->make(RequestMiddlewareInterface::class);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/canonical-context-logging.php' => config_path('canonical-context-logging.php'),
            ], 'canonical-context-logging-config');
        }

        // Register middleware if enabled
        if ($this->app->make('config')->get('canonical-context-logging.middleware.enabled', true)) {
            $this->registerMiddleware();
            $this->enforceMiddlewarePriority();
        }
    }

    /**
     * Register middleware based on Laravel version.
     * Supports Laravel 10-12 by using pushMiddleware when available,
     * with fallback for Laravel 11+ middleware configuration system.
     */
    private function registerMiddleware(): void
    {
        $kernel = $this->app->make(Kernel::class);

        // Laravel 10-12: Use pushMiddleware method if available
        // This method exists in all supported Laravel versions
        if ($kernel instanceof HttpKernel && method_exists($kernel, 'pushMiddleware')) {
            // @phpstan-ignore-next-line - pushMiddleware exists on HttpKernel but not on Kernel interface
            $kernel->pushMiddleware(CanonicalContextMiddleware::class);
            return;
        }

        // Fallback for Laravel 11+: Try to use the middleware configuration system
        // This is only used if pushMiddleware is not available (unlikely)
        if ($this->app->bound(MiddlewareConfiguration::class)) {
            $middleware = $this->app->make(MiddlewareConfiguration::class);
            $middleware->append(CanonicalContextMiddleware::class);
            return;
        }

        // Last resort: Hook into booted event for Laravel 11+
        $this->app->booted(function () {
            if ($this->app->bound(MiddlewareConfiguration::class)) {
                $middleware = $this->app->make(MiddlewareConfiguration::class);
                $middleware->append(CanonicalContextMiddleware::class);
            }
        });
    }

    /**
     * Ensure our middleware runs after authentication middleware.
     * Works for Laravel 10-12 by adjusting the kernel priority list.
     */
    private function enforceMiddlewarePriority(): void
    {
        $kernel = $this->app->make(HttpKernel::class);
        $kernelReflection = new \ReflectionObject($kernel);

        if (!$kernelReflection->hasProperty('middlewarePriority')) {
            return;
        }

        $priorityProperty = $kernelReflection->getProperty('middlewarePriority');
        $priority = $priorityProperty->getValue($kernel);

        if (!is_array($priority)) {
            return;
        }
        $authMiddleware = Authenticate::class;
        $sanctumMiddleware = 'Laravel\\Sanctum\\Http\\Middleware\\EnsureFrontendRequestsAreStateful';
        $target = CanonicalContextMiddleware::class;

        if (!in_array($authMiddleware, $priority, true)) {
            $priority[] = $authMiddleware;
        }

        $priority = array_values(array_filter($priority, fn ($middleware) => $middleware !== $target));

        $insertAfter = in_array($sanctumMiddleware, $priority, true) ? $sanctumMiddleware : $authMiddleware;
        $position = array_search($insertAfter, $priority, true);

        if ($position === false) {
            $priority[] = $target;
        } else {
            array_splice($priority, $position + 1, 0, [$target]);
        }

        $priorityProperty->setValue($kernel, $priority);
    }

    /**
     * Create OTLP exporter from config or environment.
     */
    private function createOtlpExporter($config): OtlpExporter
    {
        // Check if explicit config is provided
        $endpoint = $config->get('canonical-context-logging.exporter.otlp.endpoint');
        $protocol = $config->get('canonical-context-logging.exporter.otlp.protocol');
        $headers = $config->get('canonical-context-logging.exporter.otlp.headers', []);
        $timeout = $config->get('canonical-context-logging.exporter.otlp.timeout');

        if ($endpoint !== null || $protocol !== null || $timeout !== null) {
            // Use explicit config
            $otlpConfig = OtlpConfig::create(
                endpoint: $endpoint ?? OtlpConfig::fromEnvironment()->endpoint,
                protocol: $protocol ?? OtlpConfig::fromEnvironment()->protocol,
                headers: $headers,
                timeout: $timeout ?? OtlpConfig::fromEnvironment()->timeout
            );
            return new OtlpExporter($otlpConfig);
        }

        // Use environment variables (default)
        return new OtlpExporter();
    }
}
