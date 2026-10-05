<?php

namespace Laragear\Dte\Data;

/**
 * A parsed MIME email message, exposing its parts and text body.
 */
readonly class MimeMessage
{
    /**
     * @param  list<MimePart>  $parts
     */
    public function __construct(
        public array $parts,
        public ?string $textContent,
    ) {
        //
    }

    /**
     * Get every part of the message.
     *
     * @return list<MimePart>
     */
    public function getParts(): array
    {
        return $this->parts;
    }
}
