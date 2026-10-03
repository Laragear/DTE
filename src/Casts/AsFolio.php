<?php

namespace Laragear\Dte\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Laragear\Dte\Caf\Folio;

class AsFolio implements CastsAttributes
{
    /**
     * Cast the given value.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Folio
    {
        return new Folio(
            (int) $attributes['folio_from'],
            (int) $attributes['folio_to'],
            (int) $attributes['folio_current'],
            json_decode($attributes['folio_annuled'] ?? '[]', true)
        );
    }

    /**
     * Prepare the given value for storage.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (!$value instanceof Folio) {
            throw new InvalidArgumentException('The given value is not a Folio instance.');
        }

        return [
            'folio_from' => $value->from,
            'folio_to' => $value->to,
            'folio_current' => $value->current,
            'folio_annuled' => json_encode($value->annuled),
        ];
    }
}
