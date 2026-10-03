<?php

namespace Laragear\Dte\Database\Factories;

use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Models\SiiInboundDocumentPayload;

/** @extends DteFactory<SiiInboundDocumentPayload> */
class SiiInboundDocumentPayloadFactory extends DteFactory
{
    protected $model = SiiInboundDocumentPayload::class;

    /**
     * Return the default inbound payload attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sii_inbound_document_id' => SiiInboundDocument::factory(),
            'xml' => '<DTE/>',
        ];
    }
}
