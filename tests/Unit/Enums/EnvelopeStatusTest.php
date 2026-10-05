<?php

namespace Tests\Unit\Enums;

use Laragear\Dte\Enums\EnvelopeStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_column;

class EnvelopeStatusTest extends TestCase
{
    public static function providesTerminalStates(): array
    {
        return [
            EnvelopeStatus::Pending->value => [EnvelopeStatus::Pending, false],
            EnvelopeStatus::Assembling->value => [EnvelopeStatus::Assembling, false],
            EnvelopeStatus::Signing->value => [EnvelopeStatus::Signing, false],
            EnvelopeStatus::Signed->value => [EnvelopeStatus::Signed, false],
            EnvelopeStatus::Sending->value => [EnvelopeStatus::Sending, false],
            EnvelopeStatus::Uploaded->value => [EnvelopeStatus::Uploaded, false],
            EnvelopeStatus::Accepted->value => [EnvelopeStatus::Accepted, true],
            EnvelopeStatus::Rejected->value => [EnvelopeStatus::Rejected, true],
            EnvelopeStatus::Failed->value => [EnvelopeStatus::Failed, true],
        ];
    }

    public function test_defines_envelope_states(): void
    {
        static::assertSame(
            [
                'Pending' => 'pending',
                'Assembling' => 'assembling',
                'Signing' => 'signing',
                'Signed' => 'signed',
                'Sending' => 'sending',
                'Uploaded' => 'uploaded',
                'Accepted' => 'accepted',
                'Rejected' => 'rejected',
                'Failed' => 'failed',
            ],
            array_column(EnvelopeStatus::cases(), 'value', 'name'),
        );
        static::assertSame(EnvelopeStatus::Pending, EnvelopeStatus::DEFAULT);
    }

    #[DataProvider('providesTerminalStates')]
    public function test_identifies_terminal_states(EnvelopeStatus $status, bool $isTerminal): void
    {
        static::assertSame($status->isTerminalState(), $isTerminal);
    }

    #[DataProvider('providesTerminalStates')]
    public function test_identifies_non_terminal_states(EnvelopeStatus $status, bool $isTerminal): void
    {
        static::assertSame(! $isTerminal, $status->isNotTerminalState());
    }

    public static function providesSendableStates(): array
    {
        return [
            EnvelopeStatus::Pending->value => [EnvelopeStatus::Pending, true],
            EnvelopeStatus::Assembling->value => [EnvelopeStatus::Assembling, true],
            EnvelopeStatus::Signing->value => [EnvelopeStatus::Signing, true],
            EnvelopeStatus::Signed->value => [EnvelopeStatus::Signed, true],
            EnvelopeStatus::Sending->value => [EnvelopeStatus::Sending, false],
            EnvelopeStatus::Uploaded->value => [EnvelopeStatus::Uploaded, false],
            EnvelopeStatus::Accepted->value => [EnvelopeStatus::Accepted, false],
            EnvelopeStatus::Rejected->value => [EnvelopeStatus::Rejected, false],
            EnvelopeStatus::Failed->value => [EnvelopeStatus::Failed, false],
        ];
    }

    #[DataProvider('providesSendableStates')]
    public function test_identifies_sendable_states(EnvelopeStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isSendable());
    }

    #[DataProvider('providesSendableStates')]
    public function test_identifies_non_sendable_states(EnvelopeStatus $status, bool $expected): void
    {
        static::assertSame(! $expected, $status->isNotSendable());
    }

    public static function providesCompiledXmlStates(): array
    {
        return [
            EnvelopeStatus::Pending->value => [EnvelopeStatus::Pending, false],
            EnvelopeStatus::Assembling->value => [EnvelopeStatus::Assembling, false],
            EnvelopeStatus::Signing->value => [EnvelopeStatus::Signing, false],
            EnvelopeStatus::Signed->value => [EnvelopeStatus::Signed, true],
            EnvelopeStatus::Sending->value => [EnvelopeStatus::Sending, true],
            EnvelopeStatus::Uploaded->value => [EnvelopeStatus::Uploaded, true],
            EnvelopeStatus::Accepted->value => [EnvelopeStatus::Accepted, true],
            EnvelopeStatus::Rejected->value => [EnvelopeStatus::Rejected, true],
            EnvelopeStatus::Failed->value => [EnvelopeStatus::Failed, true],
        ];
    }

    #[DataProvider('providesCompiledXmlStates')]
    public function test_is_compiled_xml(EnvelopeStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isCompiledXml());
    }

    #[DataProvider('providesCompiledXmlStates')]
    public function test_is_not_compiled_xml(EnvelopeStatus $status, bool $expected): void
    {
        static::assertSame(! $expected, $status->isNotCompiledXml());
    }

    public static function providesDescriptions(): array
    {
        return [
            EnvelopeStatus::Pending->value => [
                EnvelopeStatus::Pending, 'El sobre está esperando documentos o su tiempo de cierre.',
            ],
            EnvelopeStatus::Assembling->value => [
                EnvelopeStatus::Assembling, 'Documentos (DTE) están añadiéndose al sobre',
            ],
            EnvelopeStatus::Signing->value => [
                EnvelopeStatus::Signing, 'El sobre está siendo firmado digitalmente (Timbre electrónico)',
            ],
            EnvelopeStatus::Signed->value => [
                EnvelopeStatus::Signed, 'El sobre ha sido firmado digitalmente (Timbre electrónico)',
            ],
            EnvelopeStatus::Sending->value => [
                EnvelopeStatus::Sending, 'El sobre firmado se está enviando al SII y aún no tiene Track ID',
            ],
            EnvelopeStatus::Uploaded->value => [
                EnvelopeStatus::Uploaded, 'El SII recibió el enviado del sobre y retornó su Track ID',
            ],
            EnvelopeStatus::Accepted->value => [EnvelopeStatus::Accepted, 'El SII aceptó el sobre completo'],
            EnvelopeStatus::Rejected->value => [EnvelopeStatus::Rejected, 'El SII rechazó el sobre en su totalidad'],
            EnvelopeStatus::Failed->value => [
                EnvelopeStatus::Failed, 'El envío del sobre falló y ningún folio fue consumido por el SII',
            ],
        ];
    }

    #[DataProvider('providesDescriptions')]
    public function test_description(EnvelopeStatus $status, string $expected): void
    {
        static::assertSame($expected, $status->description());
    }

    public static function providesLabels(): array
    {
        return [
            EnvelopeStatus::Pending->value => [EnvelopeStatus::Pending, 'Pendiente'],
            EnvelopeStatus::Assembling->value => [EnvelopeStatus::Assembling, 'Ensamblando'],
            EnvelopeStatus::Signing->value => [EnvelopeStatus::Signing, 'Firmando'],
            EnvelopeStatus::Signed->value => [EnvelopeStatus::Signed, 'Firmado'],
            EnvelopeStatus::Sending->value => [EnvelopeStatus::Sending, 'Enviando al SII'],
            EnvelopeStatus::Uploaded->value => [EnvelopeStatus::Uploaded, 'Recibido por el SII'],
            EnvelopeStatus::Accepted->value => [EnvelopeStatus::Accepted, 'Aceptado'],
            EnvelopeStatus::Rejected->value => [EnvelopeStatus::Rejected, 'Rechazado'],
            EnvelopeStatus::Failed->value => [EnvelopeStatus::Failed, 'Fallido'],
        ];
    }

    #[DataProvider('providesLabels')]
    public function test_label(EnvelopeStatus $status, string $expected): void
    {
        static::assertSame($expected, $status->label());
    }

    public static function providesRetryReasons(): array
    {
        return [
            EnvelopeStatus::Pending->value => [
                EnvelopeStatus::Pending, 'El sobre está pendiente. Procéselo para armarlo y enviarlo.',
            ],
            EnvelopeStatus::Assembling->value => [
                EnvelopeStatus::Assembling, 'El sobre se está armando. Espere a que termine o reintente después.',
            ],
            EnvelopeStatus::Signing->value => [
                EnvelopeStatus::Signing, 'El sobre se está firmando. Espere a que termine o reintente después.',
            ],
            EnvelopeStatus::Signed->value => [
                EnvelopeStatus::Signed, 'El sobre está firmado y listo para ser enviado al SII.',
            ],
            EnvelopeStatus::Sending->value => [
                EnvelopeStatus::Sending, 'El sobre se está enviando al SII. Espere a que termine o reintente después.',
            ],
            EnvelopeStatus::Uploaded->value => [
                EnvelopeStatus::Uploaded, 'El sobre fue recibido por el SII pero la respuesta está pendiente.',
            ],
            EnvelopeStatus::Failed->value => [
                EnvelopeStatus::Failed,
                'El envío del sobre falló. Sus DTE conservan el folio y pueden reempaquetarse.',
            ],
            EnvelopeStatus::Rejected->value => [
                EnvelopeStatus::Rejected,
                'El sobre fue rechazado por el SII. Sus DTE conservan el folio y pueden reempaquetarse.',
            ],
            EnvelopeStatus::Accepted->value => [
                EnvelopeStatus::Accepted, 'El sobre fue aceptado por el SII. No puede reutilizarse.',
            ],
        ];
    }

    #[DataProvider('providesRetryReasons')]
    public function test_retry_reason(EnvelopeStatus $status, string $expected): void
    {
        static::assertSame($expected, $status->retryReason());
    }

    public static function providesRetryableWithSameFolio(): array
    {
        return [
            EnvelopeStatus::Pending->value => [EnvelopeStatus::Pending, true],
            EnvelopeStatus::Assembling->value => [EnvelopeStatus::Assembling, true],
            EnvelopeStatus::Signing->value => [EnvelopeStatus::Signing, true],
            EnvelopeStatus::Signed->value => [EnvelopeStatus::Signed, true],
            EnvelopeStatus::Sending->value => [EnvelopeStatus::Sending, true],
            EnvelopeStatus::Uploaded->value => [EnvelopeStatus::Uploaded, false],
            EnvelopeStatus::Accepted->value => [EnvelopeStatus::Accepted, false],
            EnvelopeStatus::Rejected->value => [EnvelopeStatus::Rejected, false],
            EnvelopeStatus::Failed->value => [EnvelopeStatus::Failed, true],
        ];
    }

    #[DataProvider('providesRetryableWithSameFolio')]
    public function test_identifies_retryable_with_same_folio(EnvelopeStatus $status, bool $expected): void
    {
        static::assertSame($expected, $status->isRetryableWithSameFolio());
        static::assertSame(! $expected, $status->isNotRetryableWithSameFolio());
    }
}
