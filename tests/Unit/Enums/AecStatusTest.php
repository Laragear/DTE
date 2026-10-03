<?php

namespace Tests\Unit\Enums;

use Laragear\Dte\Enums\AecStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function array_column;

class AecStatusTest extends TestCase
{
    public static function providesTerminalStates(): array
    {
        return [
            AecStatus::Pending->value => [AecStatus::Pending, false],
            AecStatus::Signing->value => [AecStatus::Signing, false],
            AecStatus::Uploaded->value => [AecStatus::Uploaded, false],
            AecStatus::Accepted->value => [AecStatus::Accepted, true],
            AecStatus::Rejected->value => [AecStatus::Rejected, true],
        ];
    }

    public function test_defines_electronic_cession_states(): void
    {
        static::assertSame(
            [
                'Pending' => 'pending',
                'Signing' => 'signing',
                'Uploaded' => 'uploaded',
                'Accepted' => 'accepted',
                'Rejected' => 'rejected',
            ],
            array_column(AecStatus::cases(), 'value', 'name'),
        );
        static::assertSame(AecStatus::Pending, AecStatus::DEFAULT);
    }

    #[DataProvider(methodName: 'providesTerminalStates')]
    public function test_identifies_terminal_states(AecStatus $status, bool $isTerminal): void
    {
        static::assertSame($status->isTerminalState(), $isTerminal);
    }
}
