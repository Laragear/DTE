<?php

namespace Laragear\Dte\Contracts;

use Laragear\Dte\Data\InboundEmailData;

interface MailboxDriverInterface
{
    /**
     * Returns a list of all the email inbound
     *
     * @return iterable<int, InboundEmailData>
     */
    public function unread(): iterable;

    /**
     * Mark a previously fetched message as read.
     */
    public function markAsRead(InboundEmailData $email): void;
}
