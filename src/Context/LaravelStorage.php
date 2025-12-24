<?php

declare(strict_types=1);

namespace CanonicalContextLogging\Laravel\Context;

use CanonicalContextLogging\Context\ContextStorageInterface;
use CanonicalContextLogging\Context\EventContext;
use Illuminate\Http\Request;

/**
 * Laravel-specific storage implementation using request instance.
 * Stores EventContext in the request attributes to ensure request-scoped context.
 */
final class LaravelStorage implements ContextStorageInterface
{
    private const CONTEXT_KEY = '__canonical_context_logging_context';

    public function __construct(
        private readonly Request $request
    ) {
    }

    public function get(): ?EventContext
    {
        return $this->request->attributes->get(self::CONTEXT_KEY);
    }

    public function set(EventContext $context): void
    {
        $this->request->attributes->set(self::CONTEXT_KEY, $context);
    }

    public function clear(): void
    {
        $this->request->attributes->remove(self::CONTEXT_KEY);
    }
}
