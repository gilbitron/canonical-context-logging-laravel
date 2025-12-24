<?php

declare(strict_types=1);

use CanonicalContextLogging\Context\EventContext;
use CanonicalContextLogging\Laravel\Facades\CanonicalContext;

if (!function_exists('canonical_context')) {
    /**
     * Get the current EventContext.
     *
     * @return EventContext|null
     */
    function canonical_context(): ?EventContext
    {
        return CanonicalContext::context();
    }
}

if (!function_exists('canonical_add_context')) {
    /**
     * Add context to the current event.
     *
     * @param string $key
     * @param mixed $value
     * @return EventContext|null
     */
    function canonical_add_context(string $key, mixed $value): ?EventContext
    {
        return CanonicalContext::addContext($key, $value);
    }
}

if (!function_exists('canonical_set_error')) {
    /**
     * Set error on the current event.
     *
     * @param \Throwable|null $exception
     * @return EventContext|null
     */
    function canonical_set_error(?\Throwable $exception): ?EventContext
    {
        return CanonicalContext::setError($exception);
    }
}
