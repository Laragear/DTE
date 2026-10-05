<?php

namespace Laragear\Dte\Data;

/**
 * A single MIME part of a parsed email message.
 */
readonly class MimePart
{
    /**
     * Create a new Mime Part instance.
     */
    public function __construct(
        public string $contentType,
        public ?string $filename,
        public ?string $content,
    ) {
        //
    }
}
