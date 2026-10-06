<?php

namespace Laragear\Dte\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Hydrates a DTE payload block column into its block instance.
 *
 * @see DteBlock::castUsing()
 */
class DteBlockCast implements CastsAttributes
{
    /**
     * Create a new DTE Block Cast instance.
     *
     * @param  class-string<DteBlock>  $block
     */
    public function __construct(protected string $block)
    {
        //
    }

    /**
     * Transform the raw column value into a block instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): DteBlock
    {
        $block = $this->block;

        if ($value instanceof DteBlock) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true, 32);

            return $block::make(is_array($decoded) ? $decoded : []);
        }

        return $block::make(is_array($value) ? $value : []);
    }

    /**
     * Transform the block into a JSON string for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DteBlock) {
            return $value->toJson();
        }

        return json_encode($value);
    }
}
