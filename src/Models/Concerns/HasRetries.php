<?php

namespace Laragear\Dte\Models\Concerns;

use Closure;
use Laragear\Dte\Builders\DocumentBuilder;
use LogicException;
use function app;

trait HasRetries
{
    /**
     * Hydrate a builder from this document payload for resending.
     */
    public function retry(): DocumentBuilder
    {
        try {
            $builder = $this->document_type->builderClass();
        } catch (LogicException $e) {
            throw new LogicException("DTE type [{$this->document_type->value}] does not support retry.", previous: $e);
        }

        return app($builder)->hydrate($this);
    }

    /**
     * Apply a callback to the hydrated builder and persist changes.
     *
     * @param  Closure(DocumentBuilder): mixed  $callback
     */
    public function retryUsing(Closure $callback): static
    {
        $builder = $this->retry();

        $callback($builder);

        $builder->build();

        return $this;
    }
}
