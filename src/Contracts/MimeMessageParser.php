<?php

namespace Laragear\Dte\Contracts;

use Laragear\Dte\Data\MimeMessage;

/**
 * Parses raw MIME email payloads into their parts.
 */
interface MimeMessageParser
{
    /**
     * Parse a raw MIME email payload.
     */
    public function parse(string $raw): MimeMessage;
}
