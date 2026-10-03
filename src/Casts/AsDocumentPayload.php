<?php

namespace Laragear\Dte\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Laragear\Dte\Data\DocumentPayload;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

class AsDocumentPayload implements CastsAttributes
{
    /**
     * Cast the raw value to a DocumentPayload instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): DocumentPayload
    {
        if ($value instanceof DocumentPayload) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true, 10);

            return DocumentPayload::fromArray(is_array($decoded) ? $decoded : []);
        }

        return DocumentPayload::fromArray(is_array($value) ? $value : []);
    }

    /**
     * Prepare the value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof DocumentPayload) {
            return json_encode($value->toArray());
        }

        return json_encode(is_array($value) ? $value : []);
    }
}
