<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Laragear\Dte\Actions\InboundDte\ProcessInboundDte;
use Laragear\Dte\Mailbox\MailboxManager;
use Psr\Log\LoggerInterface;
use Throwable;

class FetchInboundMailboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:fetch-mailbox
                            {--driver= : The mailbox driver to use (defaults to config)}
                            {--sender= : Only process emails from senders containing this string}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll configured DTE mailbox for UNREAD messages and process them';

    /**
     * Execute the console command.
     */
    public function handle(
        LoggerInterface $logger,
        MailboxManager $mailbox,
        ProcessInboundDte $processor,
        Repository $config
    ): int {
        $driver = $this->resolveDriver($mailbox);
        ['prefixes' => $prefixes, 'domains' => $domains] = $this->loadDisallowedSenders($config);

        [$processed, $failed] = $this->fetchAndProcess(
            $driver, $processor, $logger, $prefixes, $domains, $this->option('sender')
        );

        $this->info("Mailbox fetch complete. Processed: $processed. Failed: $failed.");

        return self::SUCCESS;
    }

    /**
     * Resolve the mailbox driver from the option or the default.
     */
    protected function resolveDriver(MailboxManager $mailbox): mixed
    {
        $driverName = $this->option('driver');

        return $driverName ? $mailbox->driver($driverName) : $mailbox->driver();
    }

    /**
     * Load the disallowed sender prefixes and domains from configuration.
     *
     * @return array{prefixes: list<string>, domains: list<string>}
     */
    protected function loadDisallowedSenders(Repository $config): array
    {
        return [
            'prefixes' => array_filter(explode(',', (string) $config->get('dte.dim.disallowed_prefixes', ''))),
            'domains' => array_filter(explode(',', (string) $config->get('dte.dim.disallowed_domains', ''))),
        ];
    }

    /**
     * Check whether a sender address matches any disallowed prefix or domain.
     */
    protected function isSenderDisallowed(string $sender, array $prefixes, array $domains, ?string $only = null): bool
    {
        if ($only !== null && str_contains(strtolower($sender), strtolower($only))) {
            return false;
        }

        $parts = explode('@', strtolower($sender));

        return in_array($parts[0] ?? '', $prefixes, true)
            || in_array($parts[1] ?? '', $domains, true);
    }

    /**
     * Determine if the user requires only senders that match a filter.
     */
    protected function isSenderAllowed(string $sender, ?string $only = null): bool
    {
        return $only === null
            || str_contains(strtolower($sender), strtolower($only));
    }

    /**
     * Fetch unread emails, process each one, and return the success/failure counts.
     *
     * @param  list<string>  $prefixes
     * @param  list<string>  $domains
     * @return array{int, int}
     */
    protected function fetchAndProcess(
        mixed $driver,
        ProcessInboundDte $processor,
        LoggerInterface $logger,
        array $prefixes,
        array $domains,
        ?string $sender = null,
    ): array {
        $processed = 0;
        $failed = 0;

        foreach ($driver->unread() as $email) {
            try {
                if ($this->isSenderDisallowed($email->sender, $prefixes, $domains, $sender)) {
                    $driver->markAsRead($email);

                    continue;
                }

                if (!$this->isSenderAllowed($email->sender, $sender)) {
                    continue;
                }

                $processor->forEmail($email);
                $driver->markAsRead($email);

                $processed++;
            } catch (Throwable $e) {
                $failed++;

                $logger->error('Failed to process inbound DTE email.', [
                    'message_id' => $email->messageId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ]);
            }
        }

        return [$processed, $failed];
    }
}
