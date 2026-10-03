<?php

namespace Laragear\Dte\Enums;

enum EnvelopeStatus: string
{
    public const self DEFAULT = self::Pending;

    /** The envelope is waiting to be assembled. */
    case Pending = 'pending';

    /**
     * Signed documents are being added to the envelope.
     *
     * Unlike DteStatus::Building, which compiles a single document XML,
     * this collects already-compiled DTEs into the envelope payload.
     */
    case Assembling = 'assembling';

    /** The assembled envelope is being digitally signed. */
    case Signing = 'signing';

    /** The envelope has a valid digital signature. */
    case Signed = 'signed';

    /**
     * The signed envelope is being uploaded to the SII.
     *
     * Persisted before the gateway call so a crash mid-upload leaves a
     * recoverable state. Stuck envelopes return to Pending through the
     * stale-envelope recovery on the process-envelope command.
     */
    case Sending = 'sending';

    /**
     * The SII received the envelope and assigned a tracking identifier.
     *
     * Transport success at envelope granularity. Paired DTEs move from
     * Packed to Sent in the same operation. Sole pollable state: polling
     * requires a track ID assigned here.
     */
    case Uploaded = 'uploaded';

    /** The SII accepted the envelope. */
    case Accepted = 'accepted';

    /** The SII rejected the envelope. */
    case Rejected = 'rejected';

    /**
     * Processing stopped because of an unrecoverable transport error.
     *
     * Terminal at envelope granularity: the envelope is abandoned and a
     * retry requires a new envelope. Unlike DteStatus::Failed, which stays
     * non-terminal so the folio can be re-sent, the folios here were never
     * consumed by the SII — paired DTEs stay Packed and pack again.
     */
    case Failed = 'failed';

    /**
     * Determine if the envelope has reached a final state.
     */
    public function isTerminalState(): bool
    {
        return match ($this) {
            self::Accepted, self::Rejected, self::Failed => true,
            default => false,
        };
    }

    /**
     * Determine if the envelope has not reached a final state.
     */
    public function isNotTerminalState(): bool
    {
        return !$this->isTerminalState();
    }

    /**
     * Determine if the envelope can be sent to SII.
     *
     * Answers at envelope granularity: once the envelope leaves local
     * handling (Sending and beyond) there is nothing left to send.
     */
    public function isSendable(): bool
    {
        return match ($this) {
            self::Sending,
            self::Uploaded,
            self::Accepted,
            self::Rejected,
            self::Failed => false,
            default => true
        };
    }

    /**
     * Determine if the envelope cannot be sent to sII.
     */
    public function isNotSendable(): bool
    {
        return !$this->isSendable();
    }

    /**
     * Whether the envelope folios were never consumed by the SII.
     *
     * Always true while the envelope holds no SII verdict: paired DTEs
     * keep their folios and may pack into a new envelope. Mirrors the
     * DTE-side check at a coarser granularity — use it when deciding
     * whether an envelope failure frees its DTEs for repacking.
     */
    public function isRetryableWithSameFolio(): bool
    {
        return match ($this) {
            self::Pending,
            self::Assembling,
            self::Signing,
            self::Signed,
            self::Sending,
            self::Failed => true,
            default => false,
        };
    }

    /**
     * Whether the envelope folios were consumed by the SII verdict.
     */
    public function isNotRetryableWithSameFolio(): bool
    {
        return !$this->isRetryableWithSameFolio();
    }

    /**
     * Checks if the envelope status should have a compiled XML.
     */
    public function isCompiledXml(): bool
    {
        return $this !== self::Pending
            && $this !== self::Assembling
            && $this !== self::Signing;
    }

    /**
     * Checks if the envelope status should not have a compiled XML yet.
     */
    public function isNotCompiledXml(): bool
    {
        return !$this->isCompiledXml();
    }

    /**
     * Returns the friendly label of the enum.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Assembling => 'Ensamblando',
            self::Signing => 'Firmando',
            self::Signed => 'Firmado',
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
            self::Pending => 'El sobre está esperando documentos o su tiempo de cierre.',
            self::Assembling => 'Documentos (DTE) están añadiéndose al sobre',
            self::Signing => 'El sobre está siendo firmado digitalmente (Timbre electrónico)',
            self::Signed => 'El sobre ha sido firmado digitalmente (Timbre electrónico)',
            self::Sending => 'El sobre firmado se está enviando al SII y aún no tiene Track ID',
            self::Uploaded => 'El SII recibió el enviado del sobre y retornó su Track ID',
            self::Accepted => 'El SII aceptó el sobre completo',
            self::Rejected => 'El SII rechazó el sobre en su totalidad',
            self::Failed => 'El envío del sobre falló y ningún folio fue consumido por el SII',
        };
    }

    /**
     * Next step available for an envelope in this state.
     */
    public function retryReason(): string
    {
        return match ($this) {
            self::Pending => 'El sobre está pendiente. Procéselo para armarlo y enviarlo.',
            self::Assembling => 'El sobre se está armando. Espere a que termine o reintente después.',
            self::Signing => 'El sobre se está firmando. Espere a que termine o reintente después.',
            self::Signed => 'El sobre está firmado y listo para ser enviado al SII.',
            self::Sending => 'El sobre se está enviando al SII. Espere a que termine o reintente después.',
            self::Uploaded => 'El sobre fue recibido por el SII pero la respuesta está pendiente.',
            self::Failed => 'El envío del sobre falló. Sus DTE conservan el folio y pueden reempaquetarse.',
            self::Rejected => 'El sobre fue rechazado por el SII. Sus DTE conservan el folio y pueden reempaquetarse.',
            self::Accepted => 'El sobre fue aceptado por el SII. No puede reutilizarse.',
        };
    }
}
