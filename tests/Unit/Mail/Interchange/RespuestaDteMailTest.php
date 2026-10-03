<?php

namespace Tests\Unit\Mail\Interchange;

use Illuminate\Mail\Mailables\Attachment;
use Laragear\Dte\Mail\Interchange\RespuestaDteMail;
use Tests\TestCase;

class RespuestaDteMailTest extends TestCase
{
    public function test_mail_envelope_content_and_attachments(): void
    {
        $mail = new RespuestaDteMail('<xml></xml>');

        $envelope = $mail->envelope();
        static::assertSame('Acuse de Recibo DTE', $envelope->subject);

        $content = $mail->content();
        static::assertStringContainsString('Adjunto se encuentra el Acuse de Recibo Comercial', $content->htmlString);

        $attachments = $mail->attachments();
        static::assertCount(1, $attachments);
        static::assertInstanceOf(Attachment::class, $attachments[0]);
    }
}
