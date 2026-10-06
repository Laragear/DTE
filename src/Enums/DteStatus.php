<?php

namespace Laragear\Dte\Enums;

enum DteStatus: string
{
    public const self DEFAULT = self::Draft;

    /** The document is a draft and can still be modified or deleted. */
    case Draft = 'draft';

    /** The document is waiting to be processed. */
    case Pending = 'pending';

    /** The document data is being converted into XML (the payload itself). */
    case Building = 'building';

    /** The document cannot continue until an authorized folio is available. */
    case RequiresCaf = 'requires_caf';

    /** The document XML is being digitally signed. */
    case Signing = 'signing';

    /** The compiled document is finished and read-only, waiting to be picked up by an envelope. */
    case Outbox = 'outbox';

    /** The document is part of an envelope but has not been sent yet. */
    case Packed = 'packed';

    /** The document envelope has been sent to the SII in exchange of a Track ID, waiting veredict. */
    case Sent = 'sent';

    /** The SII accepted the document. */
    case Accepted = 'accepted';

    /** The SII rejected the document. */
    case Rejected = 'rejected';

    /** The document was legally annulled by a credt/debit note */
    case Annulled = 'annulled';

    /**
     * Determine if the document has reached a final state.
     */
    public function isTerminalState(): bool
    {
        return match ($this) {
            self::Accepted,
            self::Rejected,
            self::Annulled => true,
            default => false,
        };
    }

    /**
     * Determine if the document has not reached a final state.
     */
    public function isNotTerminalState(): bool
    {
        return ! $this->isTerminalState();
    }

    /**
     * Whether the document may be pushed manually into an envelope.
     */
    public function isManuallyPackable(): bool
    {
        // Document-side packability: only compiled, envelope-free states (plus
        // Draft and Pending, which compile on pack) qualify.
        return match ($this) {
            self::Draft, self::Pending, self::Outbox => true,
            default => false,
        };
    }

    /**
     * Whether the document cannot be pushed manually into an envelope.
     */
    public function isNotManuallyPackable(): bool
    {
        return ! $this->isManuallyPackable();
    }

    /**
     * Returns the friendly label of the enum.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Draft => 'Borrador',
            self::Building => 'Generando XML',
            self::RequiresCaf => 'Requiere CAF',
            self::Signing => 'Firmando',
            self::Outbox => 'En bandeja',
            self::Packed => 'Empaquetado',
            self::Sent => 'Enviado (sobre en SII)',
            self::Accepted => 'Aceptado',
            self::Rejected => 'Rechazado',
            self::Annulled => 'Anulado',
        };
    }

    /**
     * Get the detailed Spanish description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Pending => 'El documento se encuentra en la cola a la espera de ser procesado.',
            self::Draft => 'El documento es un borrador y aún puede ser modificado o eliminado.',
            self::Building => 'Los datos del documento se están convirtiendo al formato XML requerido.',
            self::RequiresCaf => 'El proceso se detuvo porque no hay folios autorizados (CAF) disponibles.',
            self::Signing => 'El documento XML está siendo firmado digitalmente.',
            self::Outbox => 'El documento está compilado y listo, a la espera de ser recogido por un sobre.',
            self::Packed => 'El documento forma parte de un sobre que aún no ha sido enviado.',
            self::Sent => 'El sobre que contiene este DTE fue recibido por el SII (Track ID asignado al sobre). Respuesta pendiente.',
            self::Accepted => 'El SII ha validado y aceptado el documento sin reparos.',
            self::Rejected => 'El SII ha rechazado el documento debido a errores en su contenido o estructura.',
            self::Annulled => 'El documento ha sido anulado legalmente ante el SII.',
        };
    }

    /**
     * Next step available for a DTE in this state.
     */
    public function retryReason(): string
    {
        return match ($this) {
            self::Draft => 'El DTE es un borrador. Constrúyalo para compilarlo y enviarlo.',
            self::Pending => 'El DTE no ha sido compilado. Compílelo y envíelo para reintentar.',
            self::Building => 'El DTE se está compilando. Espere a que termine o reintente después.',
            self::RequiresCaf => 'El DTE requiere un folio autorizado (CAF) antes de compilar.',
            self::Signing => 'El DTE se está firmando. Espere a que termine o reintente después.',
            self::Outbox => 'El DTE está compilado y listo, a la espera de ser recogido por un sobre.',
            self::Packed => 'El DTE forma parte de un sobre que aún no ha sido enviado.',
            self::Sent => 'El sobre del DTE fue enviado al SII pero la respuesta está pendiente.',
            self::Rejected => 'El DTE fue rechazado por el SII. Clone con un nuevo folio para reintentar.',
            self::Accepted => 'El DTE fue aceptado por el SII. El folio ha sido consumido y no puede reutilizarse.',
            self::Annulled => 'El DTE fue anulado legalmente. No se puede reintentar ni clonar.',
        };
    }

    /**
     * Checks if the current DTE Status has an XML payload already built.
     */
    public function isXmlPayloadPresent(): bool
    {
        return match ($this) {
            self::Draft,
            self::Pending,
            self::Building,
            self::RequiresCaf,
            self::Signing => false,
            default => true,
        };
    }

    /**
     * Checks if the current DTE Status does not have an XML payload already built.
     */
    public function isNotXmlPayloadPresent(): bool
    {
        return ! $this->isXmlPayloadPresent();
    }

    /**
     * Check if the DTE Status allows for retrying.
     */
    public function isRetryable(): bool
    {
        // Only DTEs waiting for, inside, or released from an envelope retry.
        return $this->isCompiled();
    }

    /**
     * Check if the DTE Status does not allow for retrying.
     */
    public function isNotRetryable(): bool
    {
        return ! $this->isRetryable();
    }

    /**
     * Whether the folio has not been consumed by the SII and can be re-sent.
     *
     * Answers at document granularity: every state up to and including
     * Sent keeps the folio usable. Contrast with the envelope-side mirror,
     * which reports whether an envelope failure frees its DTEs.
     */
    public function isRetryableWithSameFolio(): bool
    {
        return match ($this) {
            self::Draft, self::Pending, self::Building, self::RequiresCaf, self::Signing,
            self::Outbox, self::Packed, self::Sent => true,
            default => false,
        };
    }

    /**
     * Whether the folio was consumed by the SII and cannot be re-sent.
     */
    public function isNotRetryableWithSameFolio(): bool
    {
        return ! $this->isRetryableWithSameFolio();
    }

    /**
     * Whether the document carries a signed XML payload and is compiled.
     */
    public function isCompiled(): bool
    {
        return match ($this) {
            self::Outbox, self::Packed => true,
            default => false,
        };
    }

    /**
     * Whether the document does not carry a signed XML payload yet.
     */
    public function isNotCompiled(): bool
    {
        return ! $this->isCompiled();
    }

    /**
     * Whether the compiled document is compiled and waiting for an envelope.
     */
    public function isAwaitingEnvelope(): bool
    {
        return match ($this) {
            self::Outbox => true,
            default => false,
        };
    }

    /**
     * Whether the compiled document is not waiting for an envelope.
     */
    public function isNotAwaitingEnvelope(): bool
    {
        return ! $this->isAwaitingEnvelope();
    }

    /**
     * Whether the document may enter compilation (pending claim or retry).
     */
    public function isCompilable(): bool
    {
        return match ($this) {
            self::Pending, self::Building => true,
            default => false,
        };
    }

    /**
     * Whether the document may not enter compilation.
     */
    public function isNotCompilable(): bool
    {
        return ! $this->isCompilable();
    }

    /**
     * Whether the document is uncompiled and may compile on pack.
     */
    public function isCompilableForPack(): bool
    {
        return match ($this) {
            self::Pending => true,
            default => false,
        };
    }

    /**
     * Whether the document is not uncompiled and may not compile on pack.
     */
    public function isNotCompilableForPack(): bool
    {
        return ! $this->isCompilableForPack();
    }

    /**
     * Whether the document counts as an annulment-conflict reference.
     */
    public function isAnnulmentConflicting(): bool
    {
        return match ($this) {
            self::Pending, self::Accepted => true,
            default => false,
        };
    }

    /**
     * Whether the document does not count as an annulment-conflict reference.
     */
    public function isNotAnnulmentConflicting(): bool
    {
        return ! $this->isAnnulmentConflicting();
    }

    /**
     * Whether the outbound document may be downgraded as orphaned.
     */
    public function isOrphanDowngradable(): bool
    {
        return match ($this) {
            self::Pending, self::Sent => true,
            default => false,
        };
    }

    /**
     * Whether the outbound document may not be downgraded as orphaned.
     */
    public function isNotOrphanDowngradable(): bool
    {
        return ! $this->isOrphanDowngradable();
    }

    /**
     * Backed values of the compiled group.
     *
     * @return list<string>
     */
    public static function compiledValues(): array
    {
        return [self::Outbox->value, self::Packed->value];
    }

    /**
     * Backed values of the awaiting-envelope group.
     *
     * @return list<string>
     */
    public static function awaitingEnvelopeValues(): array
    {
        return [self::Outbox->value];
    }

    /**
     * Backed values of the compilable group.
     *
     * @return list<string>
     */
    public static function compilableValues(): array
    {
        return [self::Pending->value, self::Building->value];
    }

    /**
     * Backed values of the compilable-for-pack group.
     *
     * @return list<string>
     */
    public static function compilableForPackValues(): array
    {
        return [self::Pending->value];
    }

    /**
     * Backed values of the annulment-conflicting group.
     *
     * @return list<string>
     */
    public static function annulmentConflictingValues(): array
    {
        return [self::Pending->value, self::Accepted->value];
    }

    /**
     * Backed values of the orphan-downgradable group.
     *
     * @return list<string>
     */
    public static function orphanDowngradableValues(): array
    {
        return [self::Pending->value, self::Sent->value];
    }

    /**
     * Backed values of the non-terminal group.
     *
     * @return list<string>
     */
    public static function nonTerminalValues(): array
    {
        return [
            self::Draft->value,
            self::Pending->value,
            self::Building->value,
            self::RequiresCaf->value,
            self::Signing->value,
            self::Outbox->value,
            self::Packed->value,
            self::Sent->value,
        ];
    }
}
