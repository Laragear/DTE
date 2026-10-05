<?php

namespace Laragear\Dte\Mailbox;

use Laragear\Dte\Contracts\MimeMessageParser;
use Laragear\Dte\Data\MimeMessage;
use Laragear\Dte\Data\MimePart;
use ZBateson\MailMimeParser\Message;

/**
 * Parses raw MIME payloads using zbateson/mail-mime-parser.
 */
class ZbatesonMimeMessageParser implements MimeMessageParser
{
    /**
     * Parse a raw MIME email payload.
     */
    public function parse(string $raw): MimeMessage
    {
        $message = Message::from($raw, true);

        $parts = [];

        foreach ($message->getAllAttachmentParts() as $part) {
            $parts[] = new MimePart(
                $part->getContentType(),
                $part->getFilename(),
                $part->getContent(),
            );
        }

        return new MimeMessage($parts, $message->getTextContent());
    }
}
