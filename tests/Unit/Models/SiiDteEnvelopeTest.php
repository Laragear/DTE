<?php

namespace Tests\Unit\Models;

use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Models\SiiDteEnvelope;
use Tests\TestCase;

class SiiDteEnvelopeTest extends TestCase
{
    public function test_accepted_with_repairs_helpers(): void
    {
        $envelope = new SiiDteEnvelope;

        $envelope->status = EnvelopeStatus::Pending;
        $envelope->repairs = null;
        static::assertFalse($envelope->isAcceptedWithRepairs());
        static::assertTrue($envelope->isNotAcceptedWithRepairs());

        $envelope->status = EnvelopeStatus::Accepted;
        $envelope->repairs = null;
        static::assertFalse($envelope->isAcceptedWithRepairs());
        static::assertTrue($envelope->isNotAcceptedWithRepairs());

        $envelope->status = EnvelopeStatus::Accepted;
        $envelope->repairs = [];
        static::assertFalse($envelope->isAcceptedWithRepairs());
        static::assertTrue($envelope->isNotAcceptedWithRepairs());

        $envelope->status = EnvelopeStatus::Accepted;
        $envelope->repairs = ['rechazados' => 1];
        static::assertTrue($envelope->isAcceptedWithRepairs());
        static::assertFalse($envelope->isNotAcceptedWithRepairs());
    }

    public function test_is_not_receipt_when_type_is_documento(): void
    {
        $envelope = new SiiDteEnvelope;
        $envelope->setAttribute('type', 'documento');

        static::assertFalse($envelope->isReceipt());
        static::assertTrue($envelope->isNotReceipt());
    }

    public function test_for_dte_returns_new_instance(): void
    {
        $envelope = (new SiiDteEnvelope)->forDte([]);

        static::assertInstanceOf(SiiDteEnvelope::class, $envelope);
        static::assertFalse($envelope->exists);
    }
}
