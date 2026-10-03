<?php

namespace Tests\Unit\Mailbox;

use Laragear\Dte\Mailbox\XmlExtractor;
use Tests\TestCase;

class XmlExtractorTest extends TestCase
{
    protected function multipart(
        string $attachmentBody,
        string $contentType,
        string $filename,
        string $encoding = 'base64'
    ): string {
        if ($encoding === 'base64') {
            $body = base64_encode($attachmentBody);
        } else {
            $body = quoted_printable_encode($attachmentBody);
        }

        return
            "Content-Type: multipart/mixed; boundary=\"bnd\"\r\n\r\n"
            ."--bnd\r\n"
            ."Content-Type: text/plain\r\n\r\n"
            ."hello\r\n"
            ."--bnd\r\n"
            ."Content-Type: {$contentType}; name=\"{$filename}\"\r\n"
            ."Content-Disposition: attachment; filename=\"{$filename}\"\r\n"
            ."Content-Transfer-Encoding: {$encoding}\r\n\r\n"
            .$body
            ."\r\n--bnd--\r\n";
    }

    public function test_extracts_from_string(): void
    {
        $extractor = new XmlExtractor;

        static::assertSame('<?xml version="1.0"?>', $extractor->extractFromString('bla <?xml version="1.0"?>'));
        static::assertSame('<?xml content tag', $extractor->extractFromString(base64_encode('<?xml content tag')));
        static::assertSame('no xml at all', $extractor->extractFromString('no xml at all'));

        $b64 = base64_encode('only base64 but no xml');
        static::assertSame($b64, $extractor->extractFromString($b64));
    }

    public function test_extracts_xml_from_mime_attachment(): void
    {
        $xml = '<?xml version="1.0"?><DTE><Documento ID="F1">x</Documento></DTE>';

        $extractor = new XmlExtractor;

        static::assertSame(
            $xml,
            $extractor->extractFromRaw($this->multipart($xml, 'application/xml', 'envio.xml')),
        );
    }

    public function test_extracts_xml_by_content_type(): void
    {
        $xml = '<?xml version="1.0"?><DTE><Documento ID="F1">x</Documento></DTE>';

        $extractor = new XmlExtractor;

        static::assertSame(
            $xml,
            $extractor->extractFromRaw($this->multipart($xml, 'text/xml', 'archivo.bin')),
        );
    }

    public function test_extracts_xml_by_filename(): void
    {
        $xml = '<?xml version="1.0"?><DTE><Documento ID="F1">x</Documento></DTE>';

        $extractor = new XmlExtractor;

        static::assertSame(
            $xml,
            $extractor->extractFromRaw($this->multipart($xml, 'application/octet-stream', 'envio.xml')),
        );
    }

    public function test_handles_quoted_printable_encoding(): void
    {
        $xml = '<?xml version="1.0"?><DTE><A>1 & 2</A></DTE>';

        $extractor = new XmlExtractor;

        static::assertSame(
            $xml,
            $extractor->extractFromRaw($this->multipart($xml, 'text/xml', 'envio.xml', 'quoted-printable')),
        );
    }

    public function test_returns_empty_when_no_xml_found(): void
    {
        $extractor = new XmlExtractor;

        static::assertSame('', $extractor->extractFromRaw("Content-Type: text/plain\r\n\r\njust text, no xml here"));
        static::assertSame('', $extractor->extractFromRaw($this->multipart('nothing here', 'text/plain', 'note.txt')));
    }

    public function test_extracts_xml_from_text_body_when_no_xml_attachments(): void
    {
        // Line 47: extractFromRaw returns XML found in the text body
        // Line 83: extractXmlFromText returns substr from <?xml onward
        $xml = '<?xml version="1.0"?><DTE><Documento ID="F1">x</Documento></DTE>';
        $raw = "Content-Type: text/plain\r\n\r\n{$xml}";

        $extractor = new XmlExtractor;

        static::assertSame($xml, $extractor->extractFromRaw($raw));
    }

    public function test_extracts_xml_from_text_body_with_prefix_before_declaration(): void
    {
        $xml = '<?xml version="1.0"?><DTE><Documento ID="F1">y</Documento></DTE>';
        $raw = "Content-Type: text/plain\r\n\r\nSome preamble text\r\n{$xml}";

        $extractor = new XmlExtractor;

        static::assertSame($xml, $extractor->extractFromRaw($raw));
    }
}
