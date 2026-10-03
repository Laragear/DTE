<?php

namespace Laragear\Dte\Data;

/**
 * Track status response from SII.
 *
 * @see https://www4c.sii.cl/bolcoreinternetui/api/openapi.yaml (ResultadoEnvioDataRespuesta)
 */
readonly class TrackStatus
{
    /**
     * Create a new Track Status instance.
     */
    public function __construct(
        public string $status,
        public array $raw = [],
        public int $retryAfter = 10,
    ) {
        //
    }

    /**
     * Create from SII REST API JSON response.
     */
    public static function fromResponse(array $data, int $retryAfter = 10): static
    {
        return new self(
            status: $data['estado'] ?? 'UNKNOWN',
            raw: $data,
            retryAfter: $retryAfter,
        );
    }

    /**
     * Check if the envelope was processed successfully.
     */
    public function isProcessed(): bool
    {
        return $this->status === 'EPR';
    }

    /**
     * Check if the envelope is still being processed.
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['CRT', 'FOK', 'PRD', 'SOK'], true);
    }

    /**
     * Check if the envelope was rejected.
     */
    public function isRejected(): bool
    {
        return in_array($this->status, ['RCH', 'RCO', 'VOF', 'RFR', 'RPT', 'REC'], true);
    }
}
