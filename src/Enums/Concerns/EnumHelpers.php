<?php

namespace Laragear\Dte\Enums\Concerns;

use Illuminate\Support\Collection;

trait EnumHelpers
{
    /**
     * Returns the friendly label of the enum.
     */
    abstract public function label(): string;

    /**
     * Returns the types as a collection, optionally passed through a callback.
     *
     * @returns Collection<self>
     */
    public static function collect(): Collection
    {
        return new Collection(self::cases());
    }

    /**
     * Returns the enum as an "options" collection.
     */
    public static function options(): Collection
    {
        return self::collect()->mapWithKeys(static function (self $case): array {
            return [$case->value => $case->label()];
        });
    }
}
