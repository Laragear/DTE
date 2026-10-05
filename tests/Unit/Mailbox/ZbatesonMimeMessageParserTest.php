<?php

namespace Tests\Unit\Mailbox;

use Laragear\Dte\Data\MimeMessage;
use Laragear\Dte\Data\MimePart;
use Laragear\Dte\Mailbox\ZbatesonMimeMessageParser;
use Tests\TestCase;

class ZbatesonMimeMessageParserTest extends TestCase
{
    protected function multipart(string $body, string $contentType, string $filename): string
    {
        return
            "Content-Type: multipart/mixed; boundary=\"bnd\"\r\n\r\n"
            ."--bnd\r\n"
            ."Content-Type: text/plain\r\n\r\n"
            ."hello\r\n"
            ."--bnd\r\n"
            ."Content-Type: {$contentType}; name=\"{$filename}\"\r\n"
            ."Content-Disposition: attachment; filename=\"{$filename}\"\r\n"
            ."Content-Transfer-Encoding: base64\r\n\r\n"
            .base64_encode($body)
            ."\r\n--bnd--\r\n";
    }

    public function test_maps_attachment_parts(): void
    {
        $parser = new ZbatesonMimeMessageParser;

        $message = $parser->parse($this->multipart('<?xml version="1.0"?><DTE></DTE>', 'application/xml', 'envio.xml'));

        static::assertInstanceOf(MimeMessage::class, $message);

        $parts = $message->getParts();

        static::assertCount(1, $parts);
        static::assertInstanceOf(MimePart::class, $parts[0]);
        static::assertSame('application/xml', $parts[0]->contentType);
        static::assertSame('envio.xml', $parts[0]->filename);
        static::assertSame('<?xml version="1.0"?><DTE></DTE>', $parts[0]->content);
    }

    public function test_maps_the_text_body(): void
    {
        $parser = new ZbatesonMimeMessageParser;

        $message = $parser->parse("Content-Type: text/plain\r\n\r\njust text here");

        static::assertSame([], $message->getParts());
        static::assertSame('just text here', $message->textContent);
    }
}
