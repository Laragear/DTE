<?php

namespace Tests\Unit\Mailbox\Drivers;

use Illuminate\Contracts\Mail\Mailer as MailerContract;
use IMAP\Connection;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Mailbox\Drivers\ImapDriver;
use Laragear\Dte\Proxies\ImapProxy;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;
use const FT_UID;
use const SE_UID;

/**
 * These constants are defined to avoid testing on PHP without IMAP.
 */
if (!defined('SE_UID')) {
    define('SE_UID', 1);
}
if (!defined('FT_UID')) {
    define('FT_UID', 1);
}
if (!defined('ST_UID')) {
    define('ST_UID', 1);
}
if (!defined('SE_FREE')) {
    define('SE_FREE', 2);
}
if (!defined('SE_NOPREFETCH')) {
    define('SE_NOPREFETCH', 4);
}

class ImapDriverTest extends TestCase
{
    protected function makeDriver(array $config = []): ImapDriver
    {
        $this->config('dte.mailbox.drivers.imap', array_merge([
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'test@example.com',
            'password' => 'secret',
        ], $config));

        return $this->app->make(ImapDriver::class);
    }

    public function test_yields_unread_emails_and_extracts_xml(): void
    {
        $connection = Mockery::mock(Connection::class);

        $this->mock(ImapProxy::class, static function (MockInterface $mock) use ($connection): void {
            $mock
                ->expects('open')
                ->with('{imap.example.com:993/ssl}INBOX', 'test@example.com', 'secret')
                ->andReturn($connection);

            $mock
                ->expects('search')
                ->with($connection, 'UNSEEN', SE_UID)
                ->andReturn([10, 11, 12]);

            $mock
                ->expects('headerinfo')
                ->with($connection, 10)
                ->andReturn((object) [
                    'message_id' => 'msg-10',
                    'subject' => 'Factura 10',
                    'from' => [(object) ['mailbox' => 'sender', 'host' => 'domain.com']],
                ]);

            $mock
                ->expects('body')
                ->with($connection, 10, FT_UID)
                ->andReturn("Some text\n\n<?xml version=\"1.0\"?><EnvioDTE></EnvioDTE>");

            $mock
                ->expects('headerinfo')
                ->with($connection, 11)
                ->andReturn((object) [
                    'message_id' => 'msg-11',
                    'subject' => 'Factura 11',
                ]);

            $mock
                ->expects('body')
                ->with($connection, 11, FT_UID)
                ->andReturn("Some text\n\n".base64_encode('<?xml version="1.0"?><EnvioDTE></EnvioDTE>'));

            $mock->expects('headerinfo')->with($connection, 12)->andReturn(false);
            $mock->expects('body')->with($connection, 12, FT_UID)->andReturn(false);

            $mock->expects('close')->with($connection);
        });

        $this->mock(MailerContract::class);

        $driver = $this->makeDriver();

        $emails = iterator_to_array($driver->unread());

        static::assertCount(2, $emails);

        static::assertInstanceOf(InboundEmailData::class, $emails[0]);
        static::assertSame('msg-10', $emails[0]->messageId);
        static::assertSame('sender@domain.com', $emails[0]->sender);
        static::assertSame('Factura 10', $emails[0]->subject);
        static::assertStringContainsString('<?xml', $emails[0]->xmlAttachment);

        static::assertSame('msg-11', $emails[1]->messageId);
        static::assertSame('unknown@unknown.cl', $emails[1]->sender);
        static::assertSame('Factura 11', $emails[1]->subject);
        static::assertStringContainsString('<?xml', $emails[1]->xmlAttachment);
    }

    public function test_marks_as_read(): void
    {
        $connection = Mockery::mock(Connection::class);

        $this->mock(ImapProxy::class, static function (MockInterface $mock) use ($connection): void {
            $mock->expects('open')->andReturn($connection);

            $mock
                ->expects('search')
                ->with($connection, 'HEADER Message-ID msg-10', SE_UID)
                ->andReturn([10]);

            $mock
                ->expects('setflag_full')
                ->with($connection, '10', '\\Seen', ST_UID);

            $mock->expects('close')->with($connection);
        });

        $this->mock(MailerContract::class);

        $driver = $this->makeDriver();

        $driver->markAsRead(new InboundEmailData('msg-10', '', '', ''));
    }

    public function test_throws_exception_on_connection_failure(): void
    {
        $this->mock(ImapProxy::class, static function (MockInterface $mock): void {
            $mock->expects('open')->andReturn(false);
            $mock->expects('last_error')->andReturn('Auth failed');
        });

        $this->mock(MailerContract::class);

        $driver = $this->makeDriver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Could not connect to IMAP server: Auth failed');

        iterator_to_array($driver->unread());
    }

    public function test_returns_raw_body_if_xml_not_detected(): void
    {
        $connection = Mockery::mock(Connection::class);

        $this->mock(ImapProxy::class, static function (MockInterface $mock) use ($connection): void {
            $mock->expects('open')->andReturn($connection);

            $mock->expects('search')->andReturn([10, 11]);
            $mock->expects('close')->with($connection);

            $mock
                ->expects('headerinfo')
                ->with($connection, 10)
                ->andReturn((object) [
                    'message_id' => 'msg-10',
                    'subject' => 'Factura 10',
                    'from' => [(object) ['mailbox' => 'sender', 'host' => 'domain.com']],
                ]);

            $mock
                ->expects('body')
                ->with($connection, 10, SE_UID)
                ->andReturn('plain text email without xml');

            $mock
                ->expects('headerinfo')
                ->with($connection, 11)
                ->andReturn((object) [
                    'message_id' => 'msg-11',
                    'subject' => 'Factura 11',
                    'from' => [(object) ['mailbox' => 'sender', 'host' => 'domain.com']],
                ]);

            $mock
                ->expects('body')
                ->with($connection, 11, FT_UID)
                ->andReturn(base64_encode('plain text without xml tag'));
        });

        $this->mock(MailerContract::class);

        $driver = $this->makeDriver();
        $emails = iterator_to_array($driver->unread());

        static::assertSame('plain text email without xml', $emails[0]->xmlAttachment);
        static::assertSame(base64_encode('plain text without xml tag'), $emails[1]->xmlAttachment);
    }
}
