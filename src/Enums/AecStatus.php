<?php

namespace Laragear\Dte\Enums;

enum AecStatus: string
{
    public const self DEFAULT = self::Pending;

    /** The electronic cession file is waiting to be processed. */
    case Pending = 'pending';

    /** The electronic cession file is being digitally signed. */
    case Signing = 'signing';

    /** The SII received the electronic cession file. */
    case Uploaded = 'uploaded';

    /** The SII accepted the electronic cession. */
    case Accepted = 'accepted';

    /** The SII rejected the electronic cession. */
    case Rejected = 'rejected';

    /**
     * Determine if the electronic cession has reached a final state.
     */
    public function isTerminalState(): bool
    {
        return match ($this) {
            self::Accepted, self::Rejected => true,
            default => false,
        };
    }
}
