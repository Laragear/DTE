<?php

namespace Laragear\Dte\Enums;

enum IecvStatus: string
{
    public const self DEFAULT = self::Pending;

    /** The book is waiting to be assembled from its documents. */
    case Pending = 'pending';

    /** The book XML is being generated from the period documents. */
    case Building = 'building';

    /** The book XML is being digitally signed. */
    case Signing = 'signing';

    /** The signed book is being uploaded to the SII. */
    case Sending = 'sending';

    /** The SII received the book and assigned a tracking identifier. */
    case Uploaded = 'uploaded';

    /** The SII accepted the book. */
    case Accepted = 'accepted';

    /** The SII rejected the book. */
    case Rejected = 'rejected';

    /** Processing stopped because of an unrecoverable transport error, becoming unretryable. */
    case Failed = 'failed';

    /**
     * Determine if the book has reached a final state.
     */
    public function isTerminalState(): bool
    {
        return match ($this) {
            self::Accepted, self::Rejected, self::Failed => true,
            default => false,
        };
    }

    /**
     * Determine if the book has not reached a final state.
     */
    public function isNotTerminalState(): bool
    {
        return ! $this->isTerminalState();
    }

    /**
     * Determine if the book is waiting for the SII verdict.
     */
    public function isPollable(): bool
    {
        return $this === self::Uploaded;
    }

    /**
     * Determine if the book cannot be polled.
     */
    public function isNotPollable(): bool
    {
        return ! $this->isPollable();
    }

    /**
     * Determine if the book can be sent to the SII.
     */
    public function isSendable(): bool
    {
        // Answers at book granularity: once the book leaves local handling
        // ("Sending" and beyond) there is nothing left to send.
        return match ($this) {
            self::Sending, self::Uploaded, self::Accepted, self::Rejected, self::Failed => false,
            default => true,
        };
    }

    /**
     * Determine if the book cannot be sent to the SII.
     */
    public function isNotSendable(): bool
    {
        return ! $this->isSendable();
    }

    /**
     * Whether the period can be filed again in a new book.
     */
    public function isRetryable(): bool
    {
        // True while the SII never gave a verdict, so the book may be rebuilt and
        // re-uploaded. Mirrors the DTE-side check at a coarser granularity.
        return match ($this) {
            self::Pending, self::Building, self::Signing, self::Sending, self::Failed => true,
            default => false,
        };
    }

    /**
     * Whether the period cannot be filed again in the same book.
     */
    public function isNotRetryable(): bool
    {
        return ! $this->isRetryable();
    }

    /**
     * Returns the friendly label of the enum.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Building => 'Generando',
            self::Signing => 'Firmando',
            self::Sending => 'Enviando al SII',
            self::Uploaded => 'Recibido por el SII',
            self::Accepted => 'Aceptado',
            self::Rejected => 'Rechazado',
            self::Failed => 'Fallido',
        };
    }

    /**
     * Returns the spanish label of the case.
     */
    public function description(): string
    {
        return match ($this) {
            self::Pending => 'El libro está esperando ser generado desde sus documentos.',
            self::Building => 'El XML del libro se está generando desde los documentos del período',
            self::Signing => 'El XML del libro está siendo firmado digitalmente',
            self::Sending => 'El libro firmado se está enviando al SII y aún no tiene Track ID',
            self::Uploaded => 'El SII recibió el libro y retornó su Track ID',
            self::Accepted => 'El SII aceptó el libro completo',
            self::Rejected => 'El SII rechazó el libro',
            self::Failed => 'El envío del libro falló y el SII nunca lo recibió',
        };
    }

    /**
     * Next step available for a book in this state.
     */
    public function retryReason(): string
    {
        return match ($this) {
            self::Pending => 'El libro está pendiente. Genere y envíelo al SII.',
            self::Building => 'El libro se está generando. Espere a que termine o reintente después.',
            self::Signing => 'El libro se está firmando. Espere a que termine o reintente después.',
            self::Sending => 'El libro se está enviando al SII. Espere a que termine o reintente después.',
            self::Uploaded => 'El libro fue recibido por el SII pero la respuesta está pendiente.',
            self::Failed => 'El envío del libro falló. El período puede declararse en un libro nuevo.',
            self::Rejected => 'El libro fue rechazado por el SII. Revise los errores reportados antes de reenviar.',
            self::Accepted => 'El libro fue aceptado por el SII. El período ya fue declarado.',
        };
    }
}
