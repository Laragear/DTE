<?php

namespace Laragear\Dte\Mailbox;

use Illuminate\Support\Manager;
use Laragear\Dte\Contracts\MailboxDriverInterface;
use Laragear\Dte\Proxies\ImapProxy;
use RuntimeException;

/**
 * Resolves the configured mailbox driver for fetching and processing DTE interchange emails.
 *
 * @method MailboxDriverInterface driver(string|null $driver = null)
 */
class MailboxManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('dte.mailbox.default', 'imap');
    }

    /**
     * Create the IMAP driver instance.
     */
    protected function createImapDriver(): MailboxDriverInterface
    {
        if ($this->getContainer()->make(ImapProxy::class)->isNotExtensionEnabled()) {
            throw new RuntimeException(
                'The IMAP mailbox driver requires the "ext-imap" extension. Install it, or configure a different driver via "dte.mailbox.default".'
            );
        }

        return $this->container->make(Drivers\ImapDriver::class);
    }

    /**
     * Create the Google Workspace (Gmail) driver instance.
     */
    protected function createGoogleDriver(): MailboxDriverInterface
    {
        return $this->container->make(Drivers\GoogleWorkspaceDriver::class);
    }

    /**
     * Create the Microsoft 365 (Graph API) driver instance.
     */
    protected function createMicrosoftDriver(): MailboxDriverInterface
    {
        return $this->container->make(Drivers\Microsoft365Driver::class);
    }

    /**
     * Create the AWS SES driver instance.
     */
    protected function createAwsSesDriver(): MailboxDriverInterface
    {
        return $this->container->make(Drivers\AwsSesDriver::class);
    }
}
