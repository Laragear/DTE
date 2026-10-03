<?php

namespace Tests\Unit\Mailbox\Drivers;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;
use Google\Service\Gmail\MessagePartBody;
use Google\Service\Gmail\MessagePartHeader;
use Google\Service\Gmail\ModifyMessageRequest;
use Google\Service\Gmail\Resource\UsersMessages;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Mailbox\Drivers\GoogleWorkspaceClientFactory;
use Laragear\Dte\Mailbox\Drivers\GoogleWorkspaceDriver;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GoogleWorkspaceDriverTest extends TestCase
{
    protected function makeDriver(array $config = []): GoogleWorkspaceDriver
    {
        $this->config('dte.mailbox.batch_size', 50);
        $this->config('dte.mailbox.drivers.google', array_merge([
            'client_id' => 'id',
            'client_secret' => 'secret',
            'refresh_token' => 'token',
        ], $config));

        return $this->app->make(GoogleWorkspaceDriver::class);
    }

    public function test_yields_unread_emails_and_extracts_xml(): void
    {
        $this->mock(Client::class);

        $usersMessages = Mockery::mock(UsersMessages::class);

        $this->mock(Gmail::class, static function (MockInterface $mock) use ($usersMessages): void {
            $mock->users_messages = $usersMessages;
        });

        $listResponse = Mockery::mock();
        $stubMessage = new Message;
        $stubMessage->setId('msg1');
        $listResponse->expects('getMessages')->andReturn([$stubMessage]);

        $usersMessages
            ->expects('listUsersMessages')
            ->with('me', ['q' => 'is:unread', 'maxResults' => 50])
            ->andReturn($listResponse);

        $message = new Message;
        $message->setId('msg3');
        $message->setId('msg1');

        $header1 = new MessagePartHeader;
        $header1->setName('Message-ID');
        $header1->setValue('<msg1>');
        $header2 = new MessagePartHeader;
        $header2->setName('From');
        $header2->setValue('sender@domain.com');
        $header3 = new MessagePartHeader;
        $header3->setName('Subject');
        $header3->setValue('Test 1');

        $payload = new MessagePart;
        $payload->setHeaders([$header1, $header2, $header3]);

        $part = new MessagePart;
        $part->setMimeType('text/xml');
        $body = new MessagePartBody;
        $body->setData(strtr(base64_encode('<?xml version="1.0"?><EnvioDTE></EnvioDTE>'), ['+' => '-', '/' => '_']));
        $part->setBody($body);
        $payload->setParts([$part]);

        $message->setPayload($payload);

        $usersMessages
            ->expects('get')
            ->with('me', 'msg1', ['format' => 'full'])
            ->andReturn($message);

        $driver = $this->makeDriver();

        $emails = iterator_to_array($driver->unread());

        static::assertCount(1, $emails);

        static::assertSame('<msg1>', $emails[0]->messageId);
        static::assertSame('sender@domain.com', $emails[0]->sender);
        static::assertSame('Test 1', $emails[0]->subject);
        static::assertStringContainsString('<?xml', $emails[0]->xmlAttachment);
    }

    public function test_marks_as_read(): void
    {
        $this->mock(Client::class);

        $usersMessages = Mockery::mock(UsersMessages::class);

        $listResponse = Mockery::mock();
        $stubMessage = new Message;
        $stubMessage->setId('msg1');
        $listResponse->expects('getMessages')->andReturn([$stubMessage]);

        $usersMessages
            ->expects('listUsersMessages')
            ->with('me', ['q' => 'rfc822msgid:<msg1>', 'maxResults' => 1])
            ->andReturn($listResponse);

        $usersMessages
            ->expects('modify')
            ->withArgs(function ($userId, $messageId, ModifyMessageRequest $request) {
                return $userId === 'me' && $messageId === 'msg1' && $request->getRemoveLabelIds() === ['UNREAD'];
            });

        $this->mock(Gmail::class, static function (MockInterface $mock) use ($usersMessages): void {
            $mock->users_messages = $usersMessages;
        });

        $driver = $this->makeDriver();

        $driver->markAsRead(new InboundEmailData('<msg1>', '', '', ''));
    }

    public function test_handles_no_messages(): void
    {
        $this->mock(Client::class);
        $usersMessages = Mockery::mock(UsersMessages::class);
        $this->mock(Gmail::class, static function (MockInterface $mock) use ($usersMessages): void {
            $mock->users_messages = $usersMessages;
        });

        $listResponse = Mockery::mock();
        $listResponse->expects('getMessages')->andReturn(null);

        $usersMessages
            ->expects('listUsersMessages')
            ->andReturn($listResponse);

        $driver = $this->makeDriver();
        $emails = iterator_to_array($driver->unread());
        static::assertEmpty($emails);
    }

    public function test_handles_missing_headers_and_parts_and_caches_gmail_client(): void
    {
        $this->mock(Client::class);
        $usersMessages = Mockery::mock(UsersMessages::class);
        $this->mock(Gmail::class, static function (MockInterface $mock) use ($usersMessages): void {
            $mock->users_messages = $usersMessages;
        });

        $listResponse = Mockery::mock();
        $stubMessage = new Message;
        $stubMessage->setId('msg2');
        $listResponse->expects('getMessages')->andReturn([$stubMessage]);

        $usersMessages
            ->expects('listUsersMessages')
            ->andReturn($listResponse);

        $message = new Message;
        $message->setId('msg3');
        $message->setId('msg2');

        $payload = new MessagePart;
        $payload->setHeaders([]); // Missing headers

        $part = new MessagePart;
        $part->setMimeType('application/xml'); // Alternative mime type
        $body = new MessagePartBody;
        $body->setData(strtr(base64_encode('invalid xml'), ['+' => '-', '/' => '_']));
        $part->setBody($body);

        $payload->setParts([$part]);
        $message->setPayload($payload);

        $usersMessages->expects('get')->andReturn($message);

        $driver = $this->makeDriver();
        $emails = iterator_to_array($driver->unread());

        static::assertCount(1, $emails);
        static::assertSame('msg2', $emails[0]->messageId);
        static::assertSame('', $emails[0]->sender);
        static::assertSame('', $emails[0]->subject);
        static::assertSame('invalid xml', $emails[0]->xmlAttachment);

        // Call it again to test cached gmail() logic.
        // It should not recall setup for gmail() or just use the mock if not mocked differently.
        // Actually, $driver->unread() calls $this->gmail() - which just uses the internal var.
        $listResponse->expects('getMessages')->andReturn([]);
        $usersMessages->expects('listUsersMessages')->andReturn($listResponse);

        $emails2 = iterator_to_array($driver->unread());
        static::assertEmpty($emails2);
    }

    public function test_returns_empty_xml_when_no_xml_part_found(): void
    {
        $this->mock(Client::class);
        $usersMessages = Mockery::mock(UsersMessages::class);
        $this->mock(Gmail::class, static function (MockInterface $mock) use ($usersMessages): void {
            $mock->users_messages = $usersMessages;
        });

        $listResponse = Mockery::mock();
        $stubMessage = new Message;
        $stubMessage->setId('msg3');
        $listResponse->expects('getMessages')->andReturn([$stubMessage]);
        $usersMessages->expects('listUsersMessages')->andReturn($listResponse);

        $message = new Message;
        $message->setId('msg3');
        $payload = new MessagePart;
        $part = new MessagePart;
        $part->setMimeType('text/plain'); // NOT xml
        $payload->setParts([$part]);
        $message->setPayload($payload);

        $usersMessages->expects('get')->andReturn($message);

        $driver = $this->makeDriver();
        $emails = iterator_to_array($driver->unread());

        static::assertCount(1, $emails);
        static::assertSame('', $emails[0]->xmlAttachment);
    }

    public function test_marks_as_read_with_null_messages(): void
    {
        $this->mock(Client::class);
        $usersMessages = Mockery::mock(UsersMessages::class);
        $this->mock(Gmail::class, static function (MockInterface $mock) use ($usersMessages): void {
            $mock->users_messages = $usersMessages;
        });

        $listResponse = Mockery::mock();
        $listResponse->expects('getMessages')->andReturn(null);
        $usersMessages->expects('listUsersMessages')->andReturn($listResponse);

        $driver = $this->makeDriver();
        $driver->markAsRead(new InboundEmailData('msg_null', '', '', ''));
    }

    public function test_creates_gmail_client_via_factory_when_null(): void
    {
        $this->config('dte.mailbox.drivers.google', [
            'client_id' => 'id',
            'client_secret' => 'secret',
            'refresh_token' => 'token',
        ]);

        $mockClient = Mockery::mock(Client::class);
        $mockGmail = Mockery::mock(Gmail::class);

        $this->mock(GoogleWorkspaceClientFactory::class, static function (MockInterface $mock) use ($mockGmail): void {
            $mock
                ->expects('make')
                ->with([
                    'client_id' => 'id',
                    'client_secret' => 'secret',
                    'refresh_token' => 'token',
                ], Mockery::type(Client::class))
                ->andReturn($mockGmail);
        });

        $usersMessages = Mockery::mock(UsersMessages::class);

        $mockGmail->users_messages = $usersMessages;

        $listResponse = Mockery::mock();
        $listResponse->expects('getMessages')->andReturn([]);

        $usersMessages
            ->expects('listUsersMessages')
            ->with('me', ['q' => 'is:unread', 'maxResults' => 50])
            ->andReturn($listResponse);

        $driver = $this->app->make(GoogleWorkspaceDriver::class);

        $emails = iterator_to_array($driver->unread());

        static::assertEmpty($emails);
    }
}
