<?php

namespace Laragear\Dte\Database\Factories;

use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDteEnvelopePayload;

/** @extends DteFactory<SiiDteEnvelopePayload> */
class SiiDteEnvelopePayloadFactory extends DteFactory
{
    protected $model = SiiDteEnvelopePayload::class;

    /**
     * Return the default envelope payload attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sii_dte_envelope_id' => SiiDteEnvelope::factory(),
            'xml' => '<EnvioDTE/>',
        ];
    }
}
