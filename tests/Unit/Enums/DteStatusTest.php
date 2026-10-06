<?php

namespace Tests\Unit\Enums;

use Laragear\Dte\Enums\DteStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_column;

class DteStatusTest extends TestCase
{
    public static function providesTerminalStates(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft, false],
            DteStatus::Pending->value => [DteStatus::Pending, false],
            DteStatus::Building->value => [DteStatus::Building, false],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf, false],
            DteStatus::Signing->value => [DteStatus::Signing, false],
            DteStatus::Outbox->value => [DteStatus::Outbox, false],
            DteStatus::Packed->value => [DteStatus::Packed, false],
            DteStatus::Sent->value => [DteStatus::Sent, false],

            DteStatus::Accepted->value => [DteStatus::Accepted, true],
            DteStatus::Rejected->value => [DteStatus::Rejected, true],
            DteStatus::Annulled->value => [DteStatus::Annulled, true],
        ];
    }

    public function test_defines_document_states(): void
    {
        static::assertSame(
            [
                'Draft' => 'draft',
                'Pending' => 'pending',
                'Building' => 'building',
                'RequiresCaf' => 'requires_caf',
                'Signing' => 'signing',
                'Outbox' => 'outbox',
                'Packed' => 'packed',
                'Sent' => 'sent',
                'Accepted' => 'accepted',
                'Rejected' => 'rejected',
                'Annulled' => 'annulled',
            ],
            array_column(DteStatus::cases(), 'value', 'name'),
        );
        static::assertSame(DteStatus::Draft, DteStatus::DEFAULT);
    }

    #[DataProvider('providesTerminalStates')]
    public function test_identifies_terminal_states(DteStatus $status, bool $isTerminal): void
    {
        static::assertSame($status->isTerminalState(), $isTerminal);
        static::assertSame($status->isNotTerminalState(), ! $isTerminal);
    }

    public static function providesRetryableWithSameFolio(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft, true],
            DteStatus::Pending->value => [DteStatus::Pending, true],
            DteStatus::Building->value => [DteStatus::Building, true],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf, true],
            DteStatus::Signing->value => [DteStatus::Signing, true],
            DteStatus::Outbox->value => [DteStatus::Outbox, true],
            DteStatus::Packed->value => [DteStatus::Packed, true],
            DteStatus::Sent->value => [DteStatus::Sent, true],
            DteStatus::Accepted->value => [DteStatus::Accepted, false],
            DteStatus::Rejected->value => [DteStatus::Rejected, false],
            DteStatus::Annulled->value => [DteStatus::Annulled, false],
        ];
    }

    #[DataProvider('providesRetryableWithSameFolio')]
    public function test_identifies_retryable_with_same_folio(DteStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isRetryableWithSameFolio());
        static::assertSame(! $expected, $status->isNotRetryableWithSameFolio());
    }

    public static function providesManuallyPackable(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft, true],
            DteStatus::Pending->value => [DteStatus::Pending, true],
            DteStatus::Building->value => [DteStatus::Building, false],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf, false],
            DteStatus::Signing->value => [DteStatus::Signing, false],
            DteStatus::Outbox->value => [DteStatus::Outbox, true],
            DteStatus::Packed->value => [DteStatus::Packed, false],
            DteStatus::Sent->value => [DteStatus::Sent, false],
            DteStatus::Accepted->value => [DteStatus::Accepted, false],
            DteStatus::Rejected->value => [DteStatus::Rejected, false],
            DteStatus::Annulled->value => [DteStatus::Annulled, false],
        ];
    }

    #[DataProvider('providesManuallyPackable')]
    public function test_identifies_manually_packable(DteStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isManuallyPackable());
        static::assertSame(! $expected, $status->isNotManuallyPackable());
    }

    public static function providesLabels(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft, 'Borrador'],
            DteStatus::Pending->value => [DteStatus::Pending, 'Pendiente'],
            DteStatus::Building->value => [DteStatus::Building, 'Generando XML'],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf, 'Requiere CAF'],
            DteStatus::Signing->value => [DteStatus::Signing, 'Firmando'],
            DteStatus::Outbox->value => [DteStatus::Outbox, 'En bandeja'],
            DteStatus::Packed->value => [DteStatus::Packed, 'Empaquetado'],
            DteStatus::Sent->value => [DteStatus::Sent, 'Enviado (sobre en SII)'],
            DteStatus::Accepted->value => [DteStatus::Accepted, 'Aceptado'],
            DteStatus::Rejected->value => [DteStatus::Rejected, 'Rechazado'],
            DteStatus::Annulled->value => [DteStatus::Annulled, 'Anulado'],
        ];
    }

    #[DataProvider('providesLabels')]
    public function test_label(DteStatus $status, string $expected): void
    {
        static::assertSame($expected, $status->label());
    }

    public static function providesDescriptions(): array
    {
        return [
            DteStatus::Draft->value => [
                DteStatus::Draft, 'El documento es un borrador y aún puede ser modificado o eliminado.',
            ],
            DteStatus::Pending->value => [
                DteStatus::Pending, 'El documento se encuentra en la cola a la espera de ser procesado.',
            ],
            DteStatus::Building->value => [
                DteStatus::Building, 'Los datos del documento se están convirtiendo al formato XML requerido.',
            ],
            DteStatus::RequiresCaf->value => [
                DteStatus::RequiresCaf, 'El proceso se detuvo porque no hay folios autorizados (CAF) disponibles.',
            ],
            DteStatus::Signing->value => [DteStatus::Signing, 'El documento XML está siendo firmado digitalmente.'],
            DteStatus::Outbox->value => [
                DteStatus::Outbox, 'El documento está compilado y listo, a la espera de ser recogido por un sobre.',
            ],
            DteStatus::Packed->value => [
                DteStatus::Packed, 'El documento forma parte de un sobre que aún no ha sido enviado.',
            ],
            DteStatus::Sent->value => [
                DteStatus::Sent,
                'El sobre que contiene este DTE fue recibido por el SII (Track ID asignado al sobre). Respuesta pendiente.',
            ],
            DteStatus::Accepted->value => [
                DteStatus::Accepted, 'El SII ha validado y aceptado el documento sin reparos.',
            ],
            DteStatus::Rejected->value => [
                DteStatus::Rejected, 'El SII ha rechazado el documento debido a errores en su contenido o estructura.',
            ],
            DteStatus::Annulled->value => [DteStatus::Annulled, 'El documento ha sido anulado legalmente ante el SII.'],
        ];
    }

    #[DataProvider('providesDescriptions')]
    public function test_description(DteStatus $status, string $expected): void
    {
        static::assertSame($expected, $status->description());
    }

    public static function providesRetryReasons(): array
    {
        return [
            DteStatus::Draft->value => [
                DteStatus::Draft, 'El DTE es un borrador. Constrúyalo para compilarlo y enviarlo.',
            ],
            DteStatus::Pending->value => [
                DteStatus::Pending, 'El DTE no ha sido compilado. Compílelo y envíelo para reintentar.',
            ],
            DteStatus::Building->value => [
                DteStatus::Building, 'El DTE se está compilando. Espere a que termine o reintente después.',
            ],
            DteStatus::RequiresCaf->value => [
                DteStatus::RequiresCaf, 'El DTE requiere un folio autorizado (CAF) antes de compilar.',
            ],
            DteStatus::Signing->value => [
                DteStatus::Signing, 'El DTE se está firmando. Espere a que termine o reintente después.',
            ],
            DteStatus::Outbox->value => [
                DteStatus::Outbox, 'El DTE está compilado y listo, a la espera de ser recogido por un sobre.',
            ],
            DteStatus::Packed->value => [
                DteStatus::Packed, 'El DTE forma parte de un sobre que aún no ha sido enviado.',
            ],
            DteStatus::Sent->value => [
                DteStatus::Sent, 'El sobre del DTE fue enviado al SII pero la respuesta está pendiente.',
            ],
            DteStatus::Rejected->value => [
                DteStatus::Rejected, 'El DTE fue rechazado por el SII. Clone con un nuevo folio para reintentar.',
            ],
            DteStatus::Accepted->value => [
                DteStatus::Accepted,
                'El DTE fue aceptado por el SII. El folio ha sido consumido y no puede reutilizarse.',
            ],
            DteStatus::Annulled->value => [
                DteStatus::Annulled, 'El DTE fue anulado legalmente. No se puede reintentar ni clonar.',
            ],
        ];
    }

    #[DataProvider('providesRetryReasons')]
    public function test_retry_reason(DteStatus $status, string $expected): void
    {
        static::assertSame($expected, $status->retryReason());
    }

    public static function providesXmlPayloadPresence(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft, false],
            DteStatus::Pending->value => [DteStatus::Pending, false],
            DteStatus::Building->value => [DteStatus::Building, false],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf, false],
            DteStatus::Signing->value => [DteStatus::Signing, false],
            DteStatus::Outbox->value => [DteStatus::Outbox, true],
            DteStatus::Packed->value => [DteStatus::Packed, true],
            DteStatus::Sent->value => [DteStatus::Sent, true],
            DteStatus::Accepted->value => [DteStatus::Accepted, true],
            DteStatus::Rejected->value => [DteStatus::Rejected, true],
            DteStatus::Annulled->value => [DteStatus::Annulled, true],
        ];
    }

    #[DataProvider('providesXmlPayloadPresence')]
    public function test_is_xml_payload_present(DteStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isXmlPayloadPresent());
    }

    #[DataProvider('providesXmlPayloadPresence')]
    public function test_is_not_xml_payload_present(DteStatus $status, bool $expected): void
    {
        static::assertSame(! $expected, $status->isNotXmlPayloadPresent());
    }

    public static function providesCompiled(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft, false],
            DteStatus::Pending->value => [DteStatus::Pending, false],
            DteStatus::Building->value => [DteStatus::Building, false],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf, false],
            DteStatus::Signing->value => [DteStatus::Signing, false],
            DteStatus::Outbox->value => [DteStatus::Outbox, true],
            DteStatus::Packed->value => [DteStatus::Packed, true],
            DteStatus::Sent->value => [DteStatus::Sent, false],
            DteStatus::Accepted->value => [DteStatus::Accepted, false],
            DteStatus::Rejected->value => [DteStatus::Rejected, false],
            DteStatus::Annulled->value => [DteStatus::Annulled, false],
        ];
    }

    #[DataProvider('providesCompiled')]
    public function test_identifies_compiled(DteStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isCompiled());
        static::assertSame(! $expected, $status->isNotCompiled());
        static::assertSame($expected, $status->isRetryable());
        static::assertSame(! $expected, $status->isNotRetryable());
        static::assertSame(DteStatus::compiledValues(),
            [DteStatus::Outbox->value, DteStatus::Packed->value]);
    }

    public static function providesAwaitingEnvelope(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft, false],
            DteStatus::Pending->value => [DteStatus::Pending, false],
            DteStatus::Building->value => [DteStatus::Building, false],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf, false],
            DteStatus::Signing->value => [DteStatus::Signing, false],
            DteStatus::Outbox->value => [DteStatus::Outbox, true],
            DteStatus::Packed->value => [DteStatus::Packed, false],
            DteStatus::Sent->value => [DteStatus::Sent, false],
            DteStatus::Accepted->value => [DteStatus::Accepted, false],
            DteStatus::Rejected->value => [DteStatus::Rejected, false],
            DteStatus::Annulled->value => [DteStatus::Annulled, false],
        ];
    }

    #[DataProvider('providesAwaitingEnvelope')]
    public function test_identifies_awaiting_envelope(DteStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isAwaitingEnvelope());
        static::assertSame(! $expected, $status->isNotAwaitingEnvelope());
        static::assertSame(DteStatus::awaitingEnvelopeValues(), [DteStatus::Outbox->value]);
    }

    public static function providesCompilable(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft, false],
            DteStatus::Pending->value => [DteStatus::Pending, true],
            DteStatus::Building->value => [DteStatus::Building, true],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf, false],
            DteStatus::Signing->value => [DteStatus::Signing, false],
            DteStatus::Outbox->value => [DteStatus::Outbox, false],
            DteStatus::Packed->value => [DteStatus::Packed, false],
            DteStatus::Sent->value => [DteStatus::Sent, false],
            DteStatus::Accepted->value => [DteStatus::Accepted, false],
            DteStatus::Rejected->value => [DteStatus::Rejected, false],
            DteStatus::Annulled->value => [DteStatus::Annulled, false],
        ];
    }

    #[DataProvider('providesCompilable')]
    public function test_identifies_compilable(DteStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isCompilable());
        static::assertSame(! $expected, $status->isNotCompilable());
        static::assertSame(DteStatus::compilableValues(), [DteStatus::Pending->value, DteStatus::Building->value]);
    }

    public static function providesCompilableForPack(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft, false],
            DteStatus::Pending->value => [DteStatus::Pending, true],
            DteStatus::Building->value => [DteStatus::Building, false],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf, false],
            DteStatus::Signing->value => [DteStatus::Signing, false],
            DteStatus::Outbox->value => [DteStatus::Outbox, false],
            DteStatus::Packed->value => [DteStatus::Packed, false],
            DteStatus::Sent->value => [DteStatus::Sent, false],
            DteStatus::Accepted->value => [DteStatus::Accepted, false],
            DteStatus::Rejected->value => [DteStatus::Rejected, false],
            DteStatus::Annulled->value => [DteStatus::Annulled, false],
        ];
    }

    #[DataProvider('providesCompilableForPack')]
    public function test_identifies_compilable_for_pack(DteStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isCompilableForPack());
        static::assertSame(! $expected, $status->isNotCompilableForPack());
    }

    public static function providesAnnulmentConflicting(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft, false],
            DteStatus::Pending->value => [DteStatus::Pending, true],
            DteStatus::Building->value => [DteStatus::Building, false],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf, false],
            DteStatus::Signing->value => [DteStatus::Signing, false],
            DteStatus::Outbox->value => [DteStatus::Outbox, false],
            DteStatus::Packed->value => [DteStatus::Packed, false],
            DteStatus::Sent->value => [DteStatus::Sent, false],
            DteStatus::Accepted->value => [DteStatus::Accepted, true],
            DteStatus::Rejected->value => [DteStatus::Rejected, false],
            DteStatus::Annulled->value => [DteStatus::Annulled, false],
        ];
    }

    #[DataProvider('providesAnnulmentConflicting')]
    public function test_identifies_annulment_conflicting(DteStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isAnnulmentConflicting());
        static::assertSame(! $expected, $status->isNotAnnulmentConflicting());
        static::assertSame(DteStatus::annulmentConflictingValues(),
            [DteStatus::Pending->value, DteStatus::Accepted->value]);
    }

    public static function providesOrphanDowngradable(): array
    {
        return [
            DteStatus::Draft->value => [DteStatus::Draft, false],
            DteStatus::Pending->value => [DteStatus::Pending, true],
            DteStatus::Building->value => [DteStatus::Building, false],
            DteStatus::RequiresCaf->value => [DteStatus::RequiresCaf, false],
            DteStatus::Signing->value => [DteStatus::Signing, false],
            DteStatus::Outbox->value => [DteStatus::Outbox, false],
            DteStatus::Packed->value => [DteStatus::Packed, false],
            DteStatus::Sent->value => [DteStatus::Sent, true],
            DteStatus::Accepted->value => [DteStatus::Accepted, false],
            DteStatus::Rejected->value => [DteStatus::Rejected, false],
            DteStatus::Annulled->value => [DteStatus::Annulled, false],
        ];
    }

    #[DataProvider('providesOrphanDowngradable')]
    public function test_identifies_orphan_downgradable(DteStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isOrphanDowngradable());
        static::assertSame(! $expected, $status->isNotOrphanDowngradable());
        static::assertSame(DteStatus::orphanDowngradableValues(), [DteStatus::Pending->value, DteStatus::Sent->value]);
    }

    public function test_non_terminal_values_excludes_terminal_states(): void
    {
        static::assertSame(
            [
                DteStatus::Draft->value,
                DteStatus::Pending->value,
                DteStatus::Building->value,
                DteStatus::RequiresCaf->value,
                DteStatus::Signing->value,
                DteStatus::Outbox->value,
                DteStatus::Packed->value,
                DteStatus::Sent->value,
            ],
            DteStatus::nonTerminalValues(),
        );
    }
}
