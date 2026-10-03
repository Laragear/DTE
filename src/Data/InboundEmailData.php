<?php

namespace Laragear\Dte\Data;

readonly class InboundEmailData
{
    /**
     * Create a new Inbound Email Data instance.
     */
    public function __construct(
        public string $messageId,
        public string $sender,
        public string $subject,
        public string $xmlAttachment,
    ) {
        //
    }

    /**
     * Create a new instance fluently.
     */
    public static function make(
        string $messageId,
        string $sender,
        string $subject,
        string $xmlAttachment,
    ): static {
        return new static($messageId, $sender, $subject, $xmlAttachment);
    }
}
