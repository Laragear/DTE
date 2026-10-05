<?php

namespace Tests\Unit\Data;

use Laragear\Dte\Data\IecvTrackStatus;
use PHPUnit\Framework\TestCase;

class IecvTrackStatusTest extends TestCase
{
    public function test_accepts_the_epr_send_state(): void
    {
        $status = new IecvTrackStatus(sendState: 'EPR');

        static::assertTrue($status->isAccepted());
        static::assertFalse($status->isProcessing());
        static::assertFalse($status->isRejected());
        static::assertFalse($status->isUnknown());
    }

    public function test_treats_prd_as_processing(): void
    {
        $status = new IecvTrackStatus(sendState: 'PRD');

        static::assertTrue($status->isProcessing());
        static::assertFalse($status->isAccepted());
        static::assertFalse($status->isRejected());
    }

    public function test_treats_rsc_as_rejected(): void
    {
        $status = new IecvTrackStatus(sendState: 'RSC');

        static::assertTrue($status->isRejected());
        static::assertFalse($status->isAccepted());
    }

    public function test_detects_book_errors_independently_of_the_send_state(): void
    {
        $status = new IecvTrackStatus(sendState: 'EPR', errors: ['bad schema']);

        static::assertTrue($status->isAccepted());
        static::assertTrue($status->hasBookErrors());
    }

    public function test_reports_no_book_errors_when_the_list_is_empty(): void
    {
        static::assertFalse((new IecvTrackStatus(sendState: 'EPR'))->hasBookErrors());
    }

    public function test_detects_an_unknown_send_state(): void
    {
        $status = new IecvTrackStatus(sendState: 'ZZZ');

        static::assertTrue($status->isUnknown());
    }

    public function test_keeps_the_book_state_and_track_id(): void
    {
        $status = new IecvTrackStatus(sendState: 'EPR', bookState: 'CTR', trackId: 987654);

        static::assertSame('CTR', $status->bookState);
        static::assertSame(987654, $status->trackId);
    }
}
