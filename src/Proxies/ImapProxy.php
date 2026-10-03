<?php

namespace Laragear\Dte\Proxies;

use IMAP\Connection;
use function function_exists;

/** @internal */
class ImapProxy
{
    /**
     * Check if the IMAP extension is enabled.
     */
    public function isExtensionEnabled(): bool
    {
        return function_exists('imap_open');
    }

    /**
     * Check if the IMAP extension is not enabled.
     */
    public function isNotExtensionEnabled(): bool
    {
        return !$this->isExtensionEnabled();
    }

    /**
     * Read the header of the message.
     */
    public function headerinfo(Connection $connection, int $uid): object|false
    {
        return imap_headerinfo($connection, $uid);
    }

    /**
     * Read the message body.
     */
    public function body(Connection $connection, int $uid, int $flags = 0): string|false
    {
        return imap_body($connection, $uid, $flags);
    }

    /**
     * Close an IMAP stream.
     */
    public function close(Connection $connection): true
    {
        return imap_close($connection);
    }

    /**
     * Returns an array of messages matching the given search criteria
     *
     * @return int[]|string[]
     */
    public function search(
        Connection $connection,
        string $criteria,
        int $flags = 2,
        string $charset = '',
    ): array|false {
        return imap_search($connection, $criteria, $flags, $charset);
    }

    /**
     * Sets flags on messages
     */
    public function setflag_full(Connection $connection, string $sequence, string $flag, int $options = 0): true
    {
        return imap_setflag_full($connection, $sequence, $flag, $options);
    }

    /**
     * Open an IMAP stream to a mailbox
     */
    public function open(
        string $mailbox,
        string $user,
        string $password,
        int $flags = 0,
        int $retries = 0,
        array $options = [],
    ): Connection|false {
        return imap_open($mailbox, $user, $password, $flags, $retries, $options);
    }

    /**
     * Gets the last IMAP error that occurred during this page request.
     */
    public function last_error(): string|false
    {
        return imap_last_error();
    }
}
