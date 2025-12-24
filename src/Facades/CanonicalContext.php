<?php

declare(strict_types=1);

namespace CanonicalContextLogging\Laravel\Facades;

use CanonicalContextLogging\Context\EventContext;
use CanonicalContextLogging\Middleware\RequestMiddlewareInterface;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for accessing Canonical Context Logging functionality.
 *
 * @method static EventContext|null context() Get the current EventContext
 * @method static EventContext addContext(string $key, mixed $value) Add context to current event
 * @method static EventContext addContexts(array $contexts) Add multiple context values
 * @method static EventContext setError(?\Throwable $error) Set error on current event
 * @method static EventContext setStatus(int $statusCode) Set HTTP status code
 */
final class CanonicalContext extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return RequestMiddlewareInterface::class;
    }

    /**
     * Get the current EventContext.
     */
    public static function context(): ?EventContext
    {
        return static::getFacadeRoot()->getContext();
    }

    /**
     * Add context to the current event.
     */
    public static function addContext(string $key, mixed $value): ?EventContext
    {
        $context = static::context();
        if ($context === null) {
            return null;
        }

        $context->addContext($key, $value);
        return $context;
    }

    /**
     * Add multiple context values at once.
     */
    public static function addContexts(array $contexts): ?EventContext
    {
        $context = static::context();
        if ($context === null) {
            return null;
        }

        $context->addContexts($contexts);
        return $context;
    }

    /**
     * Set error on the current event.
     */
    public static function setError(?\Throwable $error): ?EventContext
    {
        $context = static::context();
        if ($context === null) {
            return null;
        }

        $context->setError($error);
        return $context;
    }

    /**
     * Set HTTP status code on the current event.
     */
    public static function setStatus(int $statusCode): ?EventContext
    {
        $context = static::context();
        if ($context === null) {
            return null;
        }

        $context->setStatus($statusCode);
        return $context;
    }
}
