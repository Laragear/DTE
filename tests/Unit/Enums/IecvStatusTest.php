<?php

namespace Tests\Unit\Enums;

use Laragear\Dte\Enums\IecvStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_column;

class IecvStatusTest extends TestCase
{
    public static function providesStatuses(): array
    {
        return [
            IecvStatus::Pending->value => [IecvStatus::Pending],
            IecvStatus::Building->value => [IecvStatus::Building],
            IecvStatus::Signing->value => [IecvStatus::Signing],
            IecvStatus::Sending->value => [IecvStatus::Sending],
            IecvStatus::Uploaded->value => [IecvStatus::Uploaded],
            IecvStatus::Accepted->value => [IecvStatus::Accepted],
            IecvStatus::Rejected->value => [IecvStatus::Rejected],
            IecvStatus::Failed->value => [IecvStatus::Failed],
        ];
    }

    public static function providesTerminalStates(): array
    {
        return [
            IecvStatus::Pending->value => [IecvStatus::Pending, false],
            IecvStatus::Building->value => [IecvStatus::Building, false],
            IecvStatus::Signing->value => [IecvStatus::Signing, false],
            IecvStatus::Sending->value => [IecvStatus::Sending, false],
            IecvStatus::Uploaded->value => [IecvStatus::Uploaded, false],
            IecvStatus::Accepted->value => [IecvStatus::Accepted, true],
            IecvStatus::Rejected->value => [IecvStatus::Rejected, true],
            IecvStatus::Failed->value => [IecvStatus::Failed, true],
        ];
    }

    public static function providesPollableStates(): array
    {
        return [
            IecvStatus::Pending->value => [IecvStatus::Pending, false],
            IecvStatus::Building->value => [IecvStatus::Building, false],
            IecvStatus::Signing->value => [IecvStatus::Signing, false],
            IecvStatus::Sending->value => [IecvStatus::Sending, false],
            IecvStatus::Uploaded->value => [IecvStatus::Uploaded, true],
            IecvStatus::Accepted->value => [IecvStatus::Accepted, false],
            IecvStatus::Rejected->value => [IecvStatus::Rejected, false],
            IecvStatus::Failed->value => [IecvStatus::Failed, false],
        ];
    }

    public static function providesSendableStates(): array
    {
        return [
            IecvStatus::Pending->value => [IecvStatus::Pending, true],
            IecvStatus::Building->value => [IecvStatus::Building, true],
            IecvStatus::Signing->value => [IecvStatus::Signing, true],
            IecvStatus::Sending->value => [IecvStatus::Sending, false],
            IecvStatus::Uploaded->value => [IecvStatus::Uploaded, false],
            IecvStatus::Accepted->value => [IecvStatus::Accepted, false],
            IecvStatus::Rejected->value => [IecvStatus::Rejected, false],
            IecvStatus::Failed->value => [IecvStatus::Failed, false],
        ];
    }

    public static function providesRetryableStates(): array
    {
        return [
            IecvStatus::Pending->value => [IecvStatus::Pending, true],
            IecvStatus::Building->value => [IecvStatus::Building, true],
            IecvStatus::Signing->value => [IecvStatus::Signing, true],
            IecvStatus::Sending->value => [IecvStatus::Sending, true],
            IecvStatus::Uploaded->value => [IecvStatus::Uploaded, false],
            IecvStatus::Accepted->value => [IecvStatus::Accepted, false],
            IecvStatus::Rejected->value => [IecvStatus::Rejected, false],
            IecvStatus::Failed->value => [IecvStatus::Failed, true],
        ];
    }

    public function test_defines_book_states(): void
    {
        static::assertSame(
            [
                'Pending' => 'pending',
                'Building' => 'building',
                'Signing' => 'signing',
                'Sending' => 'sending',
                'Uploaded' => 'uploaded',
                'Accepted' => 'accepted',
                'Rejected' => 'rejected',
                'Failed' => 'failed',
            ],
            array_column(IecvStatus::cases(), 'value', 'name'),
        );

        static::assertSame(IecvStatus::Pending, IecvStatus::DEFAULT);
    }

    #[DataProvider('providesTerminalStates')]
    public function test_determines_terminal_states(IecvStatus $status, bool $terminal): void
    {
        static::assertSame($terminal, $status->isTerminalState());
        static::assertSame(! $terminal, $status->isNotTerminalState());
    }

    #[DataProvider('providesPollableStates')]
    public function test_determines_pollable_states(IecvStatus $status, bool $pollable): void
    {
        static::assertSame($pollable, $status->isPollable());
        static::assertSame(! $pollable, $status->isNotPollable());
    }

    #[DataProvider('providesSendableStates')]
    public function test_determines_sendable_states(IecvStatus $status, bool $sendable): void
    {
        static::assertSame($sendable, $status->isSendable());
        static::assertSame(! $sendable, $status->isNotSendable());
    }

    #[DataProvider('providesRetryableStates')]
    public function test_determines_retryable_states(IecvStatus $status, bool $retryable): void
    {
        static::assertSame($retryable, $status->isRetryable());
        static::assertSame(! $retryable, $status->isNotRetryable());
    }

    #[DataProvider('providesStatuses')]
    public function test_returns_a_friendly_label(IecvStatus $status): void
    {
        static::assertNotSame('', $status->label());
    }

    #[DataProvider('providesStatuses')]
    public function test_returns_a_description(IecvStatus $status): void
    {
        static::assertNotSame('', $status->description());
    }

    #[DataProvider('providesStatuses')]
    public function test_returns_a_retry_reason(IecvStatus $status): void
    {
        static::assertNotSame('', $status->retryReason());
    }
}
