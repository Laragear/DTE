<?php

namespace Laragear\Dte\Mail\Interchange;

use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InterchangeEnvelopeMail extends Mailable
{
    use Queueable;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $xml,
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // We'll use a simple html string instead of a view for the library.
        return new Content(
            htmlString: '<p>Adjunto se encuentra el sobre de intercambio de DTE.</p>',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn() => $this->xml, 'envio.xml')->withMime('application/xml'),
        ];
    }
}
