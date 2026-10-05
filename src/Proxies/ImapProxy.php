<?php

namespace Laragear\Dte\Proxies;

use function function_exists;

/**
 * Proxies the ext-imap functions.
 *
 * The connection is typed as `mixed` on purpose: ext-imap returns a resource on
 * PHP < 8.4 and a final `IMAP\Connection` object from 8.4 onwards, and the driver
 * only ever hands it back to these methods without inspecting it. Pinning it to
 * either shape would make this proxy untestable wherever the other one applies.
 *
 * @internal
 */
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
        return ! $this->isExtensionEnabled();
    }

    /**
     * Read the header of the message.
     */
    public function headerinfo(mixed $connection, int $uid): object|false
    {
        return imap_headerinfo($connection, $uid);
    }

    /**
     * Read the message body.
     */
    public function body(mixed $connection, int $uid, int $flags = 0): string|false
    {
        return imap_body($connection, $uid, $flags);
    }

    /**
     * Close an IMAP stream.
     */
    public function close(mixed $connection): true
    {
        return imap_close($connection);
    }

    /**
     * Returns an array of messages matching the given search criteria
     *
     * @return int[]|string[]
     */
    public function search(
        mixed $connection,
        string $criteria,
        int $flags = 2,
        string $charset = '',
    ): array|false {
        return imap_search($connection, $criteria, $flags, $charset);
    }

    /**
     * Sets flags on messages
     */
    public function setflag_full(mixed $connection, string $sequence, string $flag, int $options = 0): true
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
    ): mixed {
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
