<?php

declare(strict_types=1);

namespace CanonicalContextLogging\Laravel\Tests\Context;

use CanonicalContextLogging\Context\EventContext;
use CanonicalContextLogging\Laravel\Context\LaravelStorage;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class LaravelStorageTest extends TestCase
{
    private LaravelStorage $storage;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new Request();
        $this->storage = new LaravelStorage($this->request);
    }

    public function testGetReturnsNullInitially(): void
    {
        $this->assertNull($this->storage->get());
    }

    public function testSetAndGet(): void
    {
        $context = new EventContext();
        $context->setService('test-service', '1.0.0');

        $this->storage->set($context);

        $retrieved = $this->storage->get();
        $this->assertNotNull($retrieved);
        $this->assertSame($context, $retrieved);
    }

    public function testClear(): void
    {
        $context = new EventContext();
        $this->storage->set($context);

        $this->assertNotNull($this->storage->get());

        $this->storage->clear();

        $this->assertNull($this->storage->get());
    }

    public function testOverwriteContext(): void
    {
        $context1 = new EventContext();
        $context1->setService('service1', '1.0.0');

        $context2 = new EventContext();
        $context2->setService('service2', '2.0.0');

        $this->storage->set($context1);
        $this->assertSame($context1, $this->storage->get());

        $this->storage->set($context2);
        $this->assertSame($context2, $this->storage->get());
        $this->assertNotSame($context1, $this->storage->get());
    }

    public function testStorageIsRequestScoped(): void
    {
        $request1 = new Request();
        $request2 = new Request();
        $storage1 = new LaravelStorage($request1);
        $storage2 = new LaravelStorage($request2);

        $context1 = new EventContext();
        $context1->setService('service1', '1.0.0');

        $context2 = new EventContext();
        $context2->setService('service2', '2.0.0');

        $storage1->set($context1);
        $storage2->set($context2);

        $this->assertSame($context1, $storage1->get());
        $this->assertSame($context2, $storage2->get());
        $this->assertNotSame($storage1->get(), $storage2->get());
    }
}
