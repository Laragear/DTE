<?php

namespace Tests\Unit\Mail\Interchange;

use Laragear\Dte\Mail\Interchange\InterchangeEnvelopeMail;
use Tests\TestCase;

class InterchangeEnvelopeMailTest extends TestCase
{
    public function test_builds_mailable(): void
    {
        $mail = new InterchangeEnvelopeMail('<xml></xml>');
        $mail->subject('Test Subject');

        $envelope = $mail->envelope();
        static::assertSame('Test Subject', $envelope->subject);

        $content = $mail->content();
        static::assertSame('<p>Adjunto se encuentra el sobre de intercambio de DTE.</p>', $content->htmlString);

        $attachments = $mail->attachments();
        static::assertCount(1, $attachments);
    }
}
