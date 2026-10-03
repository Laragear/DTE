<?php

namespace Tests\Unit\Enums;

use Laragear\Dte\Enums\InboundDteStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function array_column;

class InboundDteStatusTest extends TestCase
{
    public static function providesTerminalStatuses(): array
    {
        return [
            InboundDteStatus::Received->value => [InboundDteStatus::Received, false],
            InboundDteStatus::PhantomPending->value => [InboundDteStatus::PhantomPending, false],
            InboundDteStatus::Forged->value => [InboundDteStatus::Forged, true],
            InboundDteStatus::TechnicalRejected->value => [InboundDteStatus::TechnicalRejected, true],
            InboundDteStatus::TechnicalAccepted->value => [InboundDteStatus::TechnicalAccepted, false],
            InboundDteStatus::TechnicalDiscrepancy->value => [InboundDteStatus::TechnicalDiscrepancy, false],
            InboundDteStatus::CommercialPending->value => [InboundDteStatus::CommercialPending, false],
            InboundDteStatus::CommercialAccepted->value => [InboundDteStatus::CommercialAccepted, true],
            InboundDteStatus::CommercialRejected->value => [InboundDteStatus::CommercialRejected, true],
            InboundDteStatus::GoodsReceipt->value => [InboundDteStatus::GoodsReceipt, false],
        ];
    }

    public function test_defines_inbound_document_states(): void
    {
        static::assertSame(
            [
                'Received' => 'received',
                'PhantomPending' => 'phantom_pending',
                'Forged' => 'forged',
                'TechnicalRejected' => 'technical_rejected',
                'TechnicalAccepted' => 'technical_accepted',
                'TechnicalDiscrepancy' => 'technical_discrepancy',
                'CommercialPending' => 'commercial_pending',
                'CommercialAccepted' => 'commercial_accepted',
                'CommercialRejected' => 'commercial_rejected',
                'GoodsReceipt' => 'goods_receipt',
            ],
            array_column(InboundDteStatus::cases(), 'value', 'name'),
        );
        static::assertSame(InboundDteStatus::Received, InboundDteStatus::DEFAULT);
    }

    #[DataProvider('providesTerminalStatuses')]
    public function test_identifies_terminal_states(InboundDteStatus $status, bool $isTerminal): void
    {
        static::assertSame($status->isTerminalState(), $isTerminal);
    }
}
