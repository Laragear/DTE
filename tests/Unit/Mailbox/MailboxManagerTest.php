<?php

namespace Tests\Unit\Mailbox;

use Laragear\Dte\Contracts\MailboxDriverInterface;
use Laragear\Dte\Mailbox\Drivers\AwsSesDriver;
use Laragear\Dte\Mailbox\Drivers\GoogleWorkspaceDriver;
use Laragear\Dte\Mailbox\Drivers\ImapDriver;
use Laragear\Dte\Mailbox\Drivers\Microsoft365Driver;
use Laragear\Dte\Mailbox\MailboxManager;
use Laragear\Dte\Proxies\ImapProxy;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MailboxManagerTest extends TestCase
{
    protected function makeManager(string $default = 'imap'): MailboxManager
    {
        $this->config('dte.mailbox.default', $default);

        return $this->app->make(MailboxManager::class);
    }

    public function test_uses_the_configured_default_driver(): void
    {
        $manager = $this->makeManager('imap');

        static::assertSame('imap', $manager->getDefaultDriver());
    }

    public function test_resolves_the_mailbox_driver_interface(): void
    {
        $this->mock(AwsSesDriver::class);

        $driver = $this->makeManager('aws_ses')->driver();

        static::assertInstanceOf(MailboxDriverInterface::class, $driver);
    }

    public function test_imap_driver_requires_the_imap_extension(): void
    {
        if (function_exists('imap_open')) {
            $this->markTestSkipped('ext-imap is installed.');
        }

        $this->mock(ImapDriver::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('requires the "ext-imap" extension');

        $this->makeManager('imap')->driver();
    }

    public function test_can_resolve_different_drivers(): void
    {
        foreach (['google', 'microsoft', 'aws_ses'] as $driverName) {
            $driverClass = match ($driverName) {
                'google' => GoogleWorkspaceDriver::class,
                'microsoft' => Microsoft365Driver::class,
                'aws_ses' => AwsSesDriver::class,
            };

            $this->mock($driverClass);

            $manager = $this->makeManager($driverName);
            $driver = $manager->driver($driverName);

            static::assertInstanceOf(
                MailboxDriverInterface::class,
                $driver,
                "Driver {$driverName} must implement MailboxDriverInterface",
            );
        }
    }

    public function test_cannot_resolve_the_imap_driver_when_the_extension_is_not_available(): void
    {
        $this->mock(ImapProxy::class)->expects('isNotExtensionEnabled')->andReturnTrue();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('The IMAP mailbox driver requires the "ext-imap" extension. Install it, or configure a different driver via "dte.mailbox.default".');

        $this->makeManager('imap')->driver();
    }

    public function test_can_resolve_the_imap_driver_when_the_extension_is_available(): void
    {
        $this->mock(ImapProxy::class)->expects('isNotExtensionEnabled')->andReturnFalse();

        $this->mock(ImapDriver::class);

        static::assertInstanceOf(
            MailboxDriverInterface::class,
            $this->makeManager('imap')->driver(),
        );
    }

    public function test_can_extend_with_custom_driver(): void
    {
        $customDriver = Mockery::mock(MailboxDriverInterface::class);

        $manager = $this->makeManager('custom');

        $manager->extend('custom', fn() => $customDriver);

        static::assertSame($customDriver, $manager->driver('custom'));
    }
}
