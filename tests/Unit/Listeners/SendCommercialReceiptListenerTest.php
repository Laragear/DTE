<?php

namespace Tests\Unit\Listeners;

use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Laragear\Dte\Events\InboundDteAcknowledged;
use Laragear\Dte\Listeners\SendCommercialReceiptListener;
use Laragear\Dte\Mail\Interchange\RespuestaDteMail;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Models\SiiInterchangeLog;
use Mockery\MockInterface;
use Tests\DatabaseTestCase;

class SendCommercialReceiptListenerTest extends DatabaseTestCase
{
    public function test_sends_email_to_sender(): void
    {
        $log = SiiInterchangeLog::factory()->create([
            'sender' => 'vendor@example.com',
        ]);

        $document = SiiInboundDocument::factory()->create([
            'sii_interchange_log_id' => $log->id,
        ]);

        $event = new InboundDteAcknowledged($document, '<xml>receipt</xml>');

        $pendingMail = $this->mock(PendingMail::class, static function (MockInterface $mock): void {
            $mock->expects('send')
                ->withArgs(function ($mailable) {
                    return $mailable instanceof RespuestaDteMail
                        && $mailable->receiptXml === '<xml>receipt</xml>';
                });
        });

        $mailer = $this->mock(Mailer::class, static function (MockInterface $mock) use ($pendingMail): void {
            $mock->expects('to')
                ->with('vendor@example.com')
                ->andReturn($pendingMail);
        });

        $this->mock(Factory::class, static function (MockInterface $mock) use ($mailer): void {
            $mock->expects('mailer')->andReturn($mailer);
        });

        $listener = $this->app->make(SendCommercialReceiptListener::class);

        $this->app->call([$listener, 'handle'], ['event' => $event]);
    }

    public function test_does_not_send_email_if_sender_missing(): void
    {
        $document = SiiInboundDocument::factory()->create([
            'sii_interchange_log_id' => null,
        ]);

        $event = new InboundDteAcknowledged($document, '<xml>receipt</xml>');

        $mailer = $this->mock(Mailer::class)->expects('to')->never();
        $this->mock(Factory::class, static function (MockInterface $mock) use ($mailer): void {
            $mock->allows('mailer')->andReturn($mailer->getMock());
        });

        $listener = $this->app->make(SendCommercialReceiptListener::class);

        $this->app->call([$listener, 'handle'], ['event' => $event]);
    }
}
