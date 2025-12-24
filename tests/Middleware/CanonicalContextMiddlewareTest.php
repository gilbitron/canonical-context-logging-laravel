<?php

declare(strict_types=1);

namespace CanonicalContextLogging\Laravel\Tests\Middleware;

use CanonicalContextLogging\Context\EventContext;
use CanonicalContextLogging\Exporter\ExporterInterface;
use CanonicalContextLogging\Logger\CanonicalLogger;
use CanonicalContextLogging\Laravel\Context\LaravelStorage;
use CanonicalContextLogging\Laravel\Middleware\CanonicalContextMiddleware;
use CanonicalContextLogging\Middleware\RequestMiddlewareInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class CanonicalContextMiddlewareTest extends TestCase
{
    private CanonicalContextMiddleware $middleware;
    private RequestMiddlewareInterface&MockObject $requestMiddleware;
    private ExporterInterface&MockObject $exporter;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = $this->createMock(ExporterInterface::class);
        $logger = new CanonicalLogger($this->exporter);
        $storage = new LaravelStorage(new Request());
        $this->requestMiddleware = $this->createMock(RequestMiddlewareInterface::class);
        $this->middleware = new CanonicalContextMiddleware($this->requestMiddleware);
        $this->request = Request::create('/test', 'GET');
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('canonical-context-logging.service.name', 'test-app');
        $app['config']->set('canonical-context-logging.service.version', '1.0.0');
        $app['config']->set('canonical-context-logging.middleware.capture_user', true);
        $app['config']->set('canonical-context-logging.middleware.capture_request_headers', false);
    }

    public function testMiddlewareStartsContext(): void
    {
        $context = new EventContext();
        $context->startRequest('trace-id', 'span-id');

        $this->requestMiddleware
            ->expects($this->once())
            ->method('start')
            ->willReturn($context);

        $this->requestMiddleware
            ->expects($this->once())
            ->method('end');

        $response = $this->middleware->handle($this->request, function ($request) {
            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMiddlewareCapturesRequestMetadata(): void
    {
        $context = new EventContext();
        $context->startRequest('trace-id', 'span-id');

        $this->requestMiddleware
            ->method('start')
            ->willReturn($context);

        $this->requestMiddleware
            ->method('end')
            ->willReturnCallback(function ($ctx) {
                $data = $ctx->toArray();
                $this->assertArrayHasKey('context', $data);
                $this->assertEquals('GET', $data['context']['http.method']);
                $this->assertEquals('test', $data['context']['http.path']);
            });

        $this->middleware->handle($this->request, function ($request) {
            return new Response('OK', 200);
        });
    }

    public function testMiddlewareSetsResponseStatus(): void
    {
        $context = new EventContext();
        $context->startRequest('trace-id', 'span-id');

        $this->requestMiddleware
            ->method('start')
            ->willReturn($context);

        $this->requestMiddleware
            ->method('end')
            ->willReturnCallback(function ($ctx) {
                $data = $ctx->toArray();
                $this->assertEquals(201, $data['status']);
            });

        $this->middleware->handle($this->request, function ($request) {
            return new Response('Created', 201);
        });
    }

    public function testMiddlewareCapturesException(): void
    {
        $context = new EventContext();
        $context->startRequest('trace-id', 'span-id');

        $this->requestMiddleware
            ->method('start')
            ->willReturn($context);

        $exception = new \RuntimeException('Test exception');

        $this->requestMiddleware
            ->method('end')
            ->willReturnCallback(function ($ctx) use ($exception) {
                $data = $ctx->toArray();
                $this->assertArrayHasKey('error', $data);
                $this->assertEquals('RuntimeException', $data['error']['type']);
                $this->assertEquals('Test exception', $data['error']['message']);
                $this->assertEquals(500, $data['status']);
            });

        $this->expectException(\RuntimeException::class);

        $this->middleware->handle($this->request, function ($request) use ($exception) {
            throw $exception;
        });
    }

    public function testMiddlewareExtractsTraceIdFromHeader(): void
    {
        $this->request->headers->set('traceparent', '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01');

        $this->requestMiddleware
            ->expects($this->once())
            ->method('start')
            ->with('4bf92f3577b34da6a3ce929d0e0e4736', '00f067aa0ba902b7')
            ->willReturn(new EventContext());

        $this->requestMiddleware
            ->method('end');

        $this->middleware->handle($this->request, function ($request) {
            return new Response('OK', 200);
        });
    }

    public function testMiddlewareHandlesInvalidTraceParentHeader(): void
    {
        $this->request->headers->set('traceparent', 'invalid-format');

        $this->requestMiddleware
            ->expects($this->once())
            ->method('start')
            ->with(null, null)
            ->willReturn(new EventContext());

        $this->requestMiddleware
            ->method('end');

        $this->middleware->handle($this->request, function ($request) {
            return new Response('OK', 200);
        });
    }

    public function testMiddlewareAlwaysEndsContextEvenOnException(): void
    {
        $context = new EventContext();
        $context->startRequest('trace-id', 'span-id');

        $this->requestMiddleware
            ->method('start')
            ->willReturn($context);

        $this->requestMiddleware
            ->expects($this->once())
            ->method('end')
            ->with($context);

        $this->expectException(\RuntimeException::class);

        $this->middleware->handle($this->request, function ($request) {
            throw new \RuntimeException('Test');
        });
    }
}
