<?php

namespace Laragear\Dte\Data;

use function in_array;

/**
 * Track status response for an electronic book (IECV) from the SII.
 *
 * Unlike the envelope upload status, the books response splits the verdict in
 * two fields and reports errors in a dedicated container:
 *
 * - `EstadoEnvio` reports the reception of the file (EPR, PRD, RCT, RSC, ...).
 * - `EstadoLibro` reports the state of the book itself once received.
 * - `ErrorEnvioLibro` lists the individual rejection reasons.
 *
 * @see resources/xsd/RespSIILibros_v10.xsd
 */
readonly class IecvTrackStatus
{
    /**
     * SII reception states that mean the file was processed successfully.
     */
    public const array ACCEPTED_SEND_STATES = ['EPR'];

    /**
     * SII reception states that mean the file is still being processed.
     */
    public const array PROCESSING_SEND_STATES = ['PRD', 'SOK', 'CRT'];

    /**
     * SII reception states that mean the file was rejected outright.
     */
    public const array REJECTED_SEND_STATES = ['RSC', 'RCT', 'RCH', 'RCO', 'REC'];

    /**
     * Create a new IECV Track Status instance.
     *
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public string $sendState,
        public ?string $bookState = null,
        public array $errors = [],
        public ?int $trackId = null,
        public string $raw = '',
    ) {
        //
    }

    /**
     * Check if the SII received and processed the file.
     */
    public function isAccepted(): bool
    {
        return in_array($this->sendState, self::ACCEPTED_SEND_STATES, true);
    }

    /**
     * Check if the file is still being processed.
     */
    public function isProcessing(): bool
    {
        return in_array($this->sendState, self::PROCESSING_SEND_STATES, true);
    }

    /**
     * Check if the file was rejected.
     */
    public function isRejected(): bool
    {
        return in_array($this->sendState, self::REJECTED_SEND_STATES, true);
    }

    /**
     * Check if the SII reported individual errors for the book.
     *
     * @see self::isAccepted()
     */
    public function hasBookErrors(): bool
    {
        // The file itself can be accepted while the book carries errors, so this
        // is inspected independently of isAccepted().
        return $this->errors !== [];
    }

    /**
     * Check if the state is one the library does not recognize.
     */
    public function isUnknown(): bool
    {
        return !$this->isAccepted()
            && !$this->isProcessing()
            && !$this->isRejected();
    }
}
