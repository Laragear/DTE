<?php

namespace Laragear\Dte\Mailbox\Drivers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Laragear\Dte\Contracts\MailboxDriver;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Mailbox\XmlExtractor;
use Laragear\Dte\Proxies\ImapProxy;
use RuntimeException;

/**
 * Fetches UNREAD DTE exchange emails via traditional IMAP protocol.
 */
class ImapDriver implements MailboxDriver
{
    protected const string INBOX = 'INBOX';

    protected const string SEEN_FLAG = '\\Seen';

    /**
     * Create a new IMAP Driver instance.
     */
    public function __construct(
        protected ConfigRepository $config,
        protected ImapProxy $imap,
        protected XmlExtractor $xmlExtractor,
    ) {
        //
    }

    /**
     * Returns all unread DTE interchange emails from the IMAP mailbox.
     *
     * @return iterable<int, InboundEmailData>
     */
    public function unread(): iterable
    {
        $connection = $this->connect();

        try {
            $uids = $this->imap->search($connection, 'UNSEEN', SE_UID) ?: [];

            foreach ($uids as $uid) {
                $header = $this->imap->headerinfo($connection, $uid);
                $body = $this->imap->body($connection, $uid, FT_UID);

                if ($header === false || $body === false) {
                    continue;
                }

                // By using "unknown", we can know if the header is invalid or not.
                $from = $header->from[0] ?? (object) ['mailbox' => 'unknown', 'host' => 'unknown.cl'];

                yield new InboundEmailData(
                    messageId: trim($header->message_id ?? ''),
                    sender: $from ? $from->mailbox.'@'.$from->host : '',
                    subject: $header->subject ?? '',
                    xmlAttachment: $this->xmlExtractor->extractFromString($body),
                );
            }
        } finally {
            $this->imap->close($connection);
        }
    }

    /**
     * Mark a previously fetched message as read in the IMAP mailbox.
     */
    public function markAsRead(InboundEmailData $email): void
    {
        $connection = $this->connect();

        try {
            $ids = $this->imap->search($connection, 'HEADER Message-ID '.trim($email->messageId), SE_UID) ?: [];

            foreach ($ids as $uid) {
                $this->imap->setflag_full($connection, (string) $uid, static::SEEN_FLAG, ST_UID);
            }
        } finally {
            $this->imap->close($connection);
        }
    }

    /**
     * Open an authenticated IMAP connection using the configured credentials.
     *
     * @return resource
     */
    protected function connect(): mixed
    {
        $config = $this->driverConfig();

        $mailbox = sprintf(
            '{%s:%d/%s}%s',
            $config['host'],
            $config['port'] ?? 993,
            $config['encryption'] ?? 'ssl',
            static::INBOX,
        );

        $connection = $this->imap->open($mailbox, $config['username'] ?? '', $config['password'] ?? '');

        if ($connection === false) {
            throw new RuntimeException('Could not connect to IMAP server: '.$this->imap->last_error());
        }

        return $connection;
    }

    /**
     * Return the IMAP driver configuration.
     *
     * @return array<string, mixed>
     */
    protected function driverConfig(): array
    {
        return $this->config->get('dte.mailbox.drivers.imap', []);
    }
}
