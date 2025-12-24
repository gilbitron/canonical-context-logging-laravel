<?php

declare(strict_types=1);

namespace CanonicalContextLogging\Laravel\Tests;

use CanonicalContextLogging\Context\ContextStorageInterface;
use CanonicalContextLogging\Exporter\ExporterInterface;
use CanonicalContextLogging\Laravel\CanonicalContextLoggingServiceProvider;
use CanonicalContextLogging\Laravel\Context\LaravelStorage;
use CanonicalContextLogging\Logger\CanonicalLogger;
use CanonicalContextLogging\Middleware\RequestMiddleware;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;

final class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            CanonicalContextLoggingServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('canonical-context-logging.exporter.type', 'console');
    }

    public function testServiceProviderRegistersContextStorage(): void
    {
        $storage = $this->app->make(ContextStorageInterface::class);

        $this->assertInstanceOf(LaravelStorage::class, $storage);
    }

    public function testServiceProviderRegistersExporter(): void
    {
        $exporter = $this->app->make(ExporterInterface::class);

        $this->assertInstanceOf(ExporterInterface::class, $exporter);
    }

    public function testServiceProviderRegistersLogger(): void
    {
        $logger = $this->app->make(CanonicalLogger::class);

        $this->assertInstanceOf(CanonicalLogger::class, $logger);
    }

    public function testServiceProviderRegistersRequestMiddleware(): void
    {
        $middleware = $this->app->make(RequestMiddleware::class);

        $this->assertInstanceOf(RequestMiddleware::class, $middleware);
    }

    public function testServiceProviderRegistersConsoleExporter(): void
    {
        $this->app['config']->set('canonical-context-logging.exporter.type', 'console');

        $exporter = $this->app->make(ExporterInterface::class);

        $this->assertInstanceOf(\CanonicalContextLogging\Exporter\ConsoleExporter::class, $exporter);
    }

    public function testServiceProviderRegistersFileExporter(): void
    {
        $this->app['config']->set('canonical-context-logging.exporter.type', 'file');
        $this->app['config']->set('canonical-context-logging.exporter.file.path', '/tmp/test.jsonl');

        $exporter = $this->app->make(ExporterInterface::class);

        $this->assertInstanceOf(\CanonicalContextLogging\Exporter\FileExporter::class, $exporter);
    }

    public function testServiceProviderRegistersOtlpExporter(): void
    {
        $this->app['config']->set('canonical-context-logging.exporter.type', 'otlp');
        $this->app['config']->set('canonical-context-logging.exporter.otlp.endpoint', 'http://localhost:4318');

        $exporter = $this->app->make(ExporterInterface::class);

        $this->assertInstanceOf(\CanonicalContextLogging\Exporter\OtlpExporter::class, $exporter);
    }

    public function testServiceProviderThrowsExceptionForUnknownExporter(): void
    {
        $this->app['config']->set('canonical-context-logging.exporter.type', 'unknown');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown exporter type: unknown');

        $this->app->make(ExporterInterface::class);
    }

    public function testServiceProviderMergesConfig(): void
    {
        $config = $this->app->make('config');

        $this->assertNotNull($config->get('canonical-context-logging.exporter.type'));
        $this->assertNotNull($config->get('canonical-context-logging.logger.slow_request_threshold'));
    }
}
