<?php

namespace Laragear\Dte\Mailbox;

use ZBateson\MailMimeParser\Message;

class XmlExtractor
{
    /**
     * Extracts XML from a raw email payload using standards-compliant MIME parsing.
     */
    public function extractFromRaw(string $raw): string
    {
        $message = Message::from($raw, true);

        // First, try to find XML attachments identified by their content type.
        foreach ($message->getAllAttachmentParts() as $part) {
            if (in_array($part->getContentType(), ['text/xml', 'application/xml'], true)) {
                $content = $part->getContent();

                if ($content !== null && str_contains($content, '<?xml')) {
                    return $content;
                }
            }
        }

        // If there are none, we can always find attachments with an `.xml` filename.
        foreach ($message->getAllAttachmentParts() as $part) {
            $filename = $part->getFilename();

            if ($filename !== null && str_ends_with(strtolower($filename), '.xml')) {
                $content = $part->getContent();

                if ($content !== null && str_contains($content, '<?xml')) {
                    return $content;
                }
            }
        }

        // Finally, resort to find the XML embedded directly in the text body.
        $text = $message->getTextContent();

        if ($text !== null) {
            $extracted = $this->extractXmlFromText($text);

            if ($extracted !== '') {
                return $extracted;
            }
        }

        return '';
    }

    /**
     * Extracts XML from a generic string payload, often already-decoded content.
     */
    public function extractFromString(string $body): string
    {
        if (str_contains($body, '<?xml')) {
            return substr($body, (int) strpos($body, '<?xml'));
        }

        $decoded = base64_decode($body, true);

        if ($decoded !== false && $decoded !== '' && str_contains($decoded, '<?xml')) {
            return $decoded;
        }

        return $body;
    }

    /**
     * Pulls the XML declaration onward out of a larger text blob.
     */
    protected function extractXmlFromText(string $text): string
    {
        $start = strpos($text, '<?xml');

        if ($start === false) {
            return '';
        }

        return substr($text, $start);
    }
}
