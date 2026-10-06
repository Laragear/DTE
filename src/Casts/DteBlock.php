<?php

namespace Laragear\Dte\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use XMLWriter;
use function array_merge;
use function is_array;
use function iterator_to_array;
use function validator;

abstract class DteBlock extends Fluent implements Castable
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = [];

    /**
     * Create a new block instance, applying defaults for missing keys.
     *
     * @param  iterable<string, mixed>  $attributes
     */
    public static function make($attributes = []): static
    {
        return new static(array_merge(
            static::defaults(),
            is_array($attributes) ? $attributes : iterator_to_array($attributes),
        ));
    }

    /**
     * The default attributes applied when a key is missing.
     *
     * @return array<string, mixed>
     */
    protected static function defaults(): array
    {
        return [];
    }

    /**
     * Validate the block attributes against the XSD-derived rules.
     */
    public function validate(): void
    {
        validator($this->toArray(), static::RULES)->validate();
    }

    /**
     * Append the block as valid XML for the DTE legal document.
     */
    abstract public function toXml(XMLWriter $writer): void;

    /*
     |--------------------------------------------------------------------------
     | Eloquent cast
     |--------------------------------------------------------------------------
     */

    /**
     * Return the caster hydrating this block from its column.
     */
    public static function castUsing(array $arguments): DteBlockCast
    {
        return new DteBlockCast(static::class);
    }

    /*
     |--------------------------------------------------------------------------
     | Fluent snake_case access
     |--------------------------------------------------------------------------
     */

    /**
     * Handle dynamic calls to set attributes using snake_case keys.
     *
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     */
    public function __call($method, $parameters): mixed
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        $this->attributes[Str::snake($method)] = $parameters === [] ? true : $parameters[0];

        return $this;
    }

    /**
     * Dynamically retrieve an attribute by its raw or camelCase key.
     *
     * @param  string  $key
     */
    public function __get($key): mixed
    {
        return $this->value($key) ?? $this->value(Str::snake($key));
    }

    /*
     |--------------------------------------------------------------------------
     | XML helpers
     |--------------------------------------------------------------------------
     */

    /**
     * Append an element only when its value is present.
     */
    protected function optionalElement(XMLWriter $writer, string $name, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $writer->writeElement($name, (string) $value);
        }
    }

    /**
     * Append mapped non-empty values.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $fields
     */
    protected function optionalElements(XMLWriter $writer, array $data, array $fields): void
    {
        foreach ($fields as $key => $name) {
            $this->optionalElement($writer, $name, $data[$key] ?? null);
        }
    }

    /**
     * Append a positive numeric element.
     */
    protected function positiveElement(XMLWriter $writer, string $name, int|float $value): void
    {
        if ($value > 0) {
            $writer->writeElement($name, (string) $value);
        }
    }

    /**
     * Format a decimal without insignificant trailing zeroes.
     */
    protected function decimal(int|float $value): string
    {
        $formatted = number_format($value, 6, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
