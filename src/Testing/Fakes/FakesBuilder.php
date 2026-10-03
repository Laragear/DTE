<?php

namespace Laragear\Dte\Testing\Fakes;

trait FakesBuilder
{
    /**
     * Swap the builder with a fake that captures state instead of persisting.
     *
     * @return FakeDocumentBuilder|static
     */
    public static function fake()
    {
        // Also binds the concrete builder class so dependency injection
        // receives the fake (e.g., classes that type-hint InvoiceBuilder).
        $fakeClass = static::$fakeClass;

        $fake = app($fakeClass);

        static::swap($fakeClass, $fake);

        return $fake;
    }

    /**
     * Restore the original builder binding.
     */
    public static function restore(): void
    {
        static::clearResolvedInstances();

        app()->forgetInstance(static::$fakeClass);
    }
}
