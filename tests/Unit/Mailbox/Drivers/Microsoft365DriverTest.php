<?php

namespace Tests\Unit\Mailbox\Drivers;

use GuzzleHttp\Psr7\Utils;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Mailbox\Drivers\Microsoft365ClientFactory;
use Laragear\Dte\Mailbox\Drivers\Microsoft365Driver;
use Microsoft\Graph\Generated\Models\EmailAddress;
use Microsoft\Graph\Generated\Models\FileAttachment;
use Microsoft\Graph\Generated\Models\ItemAttachment;
use Microsoft\Graph\Generated\Models\Message;
use Microsoft\Graph\Generated\Models\Recipient;
use Microsoft\Graph\Generated\Users\Item\Messages\Item\MessageItemRequestBuilder;
use Microsoft\Graph\Generated\Users\Item\Messages\MessagesRequestBuilder;
use Microsoft\Graph\Generated\Users\Item\Messages\MessagesRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\UserItemRequestBuilder;
use Microsoft\Graph\GraphServiceClient;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class Microsoft365DriverTest extends TestCase
{
    protected function makeDriver(array $config = []): Microsoft365Driver
    {
        $this->config('dte.mailbox.batch_size', 50);
        $this->config('dte.mailbox.drivers.microsoft', array_merge([
            'tenant_id' => 'tenant',
            'client_id' => 'id',
            'client_secret' => 'secret',
        ], $config));

        return $this->app->make(Microsoft365Driver::class);
    }

    public function test_yields_unread_emails_and_extracts_xml(): void
    {
        $this->mock(GraphServiceClient::class, static function (MockInterface $mock): void {
            $userBuilder = Mockery::mock(UserItemRequestBuilder::class);
            $messagesBuilder = Mockery::mock(MessagesRequestBuilder::class);

            $mock->expects('me')->andReturn($userBuilder);
            $userBuilder->expects('messages')->andReturn($messagesBuilder);

            $message1 = new Message;
            $message1->setInternetMessageId('<msg1>');
            $message1->setSubject('Test 1');
            $from = new Recipient;
            $email = new EmailAddress;
            $email->setAddress('sender@domain.com');
            $from->setEmailAddress($email);
            $message1->setFrom($from);

            $attachment = new FileAttachment;
            $attachment->setContentType('text/xml');
            $attachment->setContentBytes(Utils::streamFor(base64_encode(
                '<?xml version="1.0"?><EnvioDTE></EnvioDTE>',
            )));
            $message1->setAttachments([$attachment]);

            $message2 = new Message;
            $message2->setId('msg2');
            $message2->setInternetMessageId(null);

            $messagesResponse = Mockery::mock();
            $messagesResponse->expects('getValue')->andReturn([$message1, $message2]);

            $promise = new FulfilledPromise($messagesResponse);

            $messagesBuilder
                ->expects('get')
                ->withArgs(function (
                    MessagesRequestBuilderGetRequestConfiguration $config,
                ) {
                    return
                        $config->queryParameters->filter === 'isRead eq false'
                        && $config->queryParameters->top === 50
                        && $config->queryParameters->expand === ['attachments'];
                })
                ->andReturn($promise);
        });

        $driver = $this->makeDriver();

        $emails = iterator_to_array($driver->unread());

        static::assertCount(2, $emails);

        static::assertSame('<msg1>', $emails[0]->messageId);
        static::assertSame('sender@domain.com', $emails[0]->sender);
        static::assertSame('Test 1', $emails[0]->subject);
        static::assertStringContainsString('<?xml', $emails[0]->xmlAttachment);

        static::assertSame('msg2', $emails[1]->messageId);
        static::assertSame('', $emails[1]->xmlAttachment);
    }

    public function test_marks_as_read(): void
    {
        $this->mock(GraphServiceClient::class, static function (MockInterface $mock): void {
            $userBuilder = Mockery::mock(UserItemRequestBuilder::class);
            $messagesBuilder = Mockery::mock(MessagesRequestBuilder::class);

            $mock->expects('me')->twice()->andReturn($userBuilder);
            $userBuilder->expects('messages')->twice()->andReturn($messagesBuilder);

            $message1 = new Message;
            $message1->setId('123');

            $messagesResponse = Mockery::mock();
            $messagesResponse->expects('getValue')->andReturn([$message1]);

            $promise = new FulfilledPromise($messagesResponse);

            $messagesBuilder
                ->expects('get')
                ->withArgs(function (
                    MessagesRequestBuilderGetRequestConfiguration $config,
                ) {
                    return
                        $config->queryParameters->filter === "internetMessageId eq '<msg1>'"
                        && $config->queryParameters->top === 1;
                })
                ->andReturn($promise);

            $messageItemBuilder = Mockery::mock(MessageItemRequestBuilder::class);
            $messagesBuilder->expects('byMessageId')->with('123')->andReturn($messageItemBuilder);

            $patchPromise = new FulfilledPromise('ok');

            $messageItemBuilder
                ->expects('patch')
                ->withArgs(function (Message $update) {
                    return $update->getIsRead() === true;
                })
                ->andReturn($patchPromise);
        });

        $driver = $this->makeDriver();

        $driver->markAsRead(new InboundEmailData('<msg1>', '', '', ''));
    }

    public function test_creates_client_via_factory_when_null(): void
    {
        $this->config('dte.mailbox.drivers.microsoft', [
            'tenant_id' => 'tenant1',
            'client_id' => 'client1',
            'client_secret' => 'secret1',
        ]);

        $mockClient = Mockery::mock(GraphServiceClient::class);

        $this->mock(Microsoft365ClientFactory::class, static function (MockInterface $mock) use ($mockClient): void {
            $mock
                ->expects('make')
                ->with([
                    'tenant_id' => 'tenant1',
                    'client_id' => 'client1',
                    'client_secret' => 'secret1',
                ])
                ->andReturn($mockClient);
        });

        $userBuilder = Mockery::mock(UserItemRequestBuilder::class);
        $messagesBuilder = Mockery::mock(MessagesRequestBuilder::class);

        $mockClient->expects('me')->andReturn($userBuilder);
        $userBuilder->expects('messages')->andReturn($messagesBuilder);

        $messagesResponse = Mockery::mock();
        $messagesResponse->expects('getValue')->andReturn([]);

        $messagesBuilder->expects('get')->andReturn(new FulfilledPromise($messagesResponse));

        $driver = $this->app->make(Microsoft365Driver::class);

        $emails = iterator_to_array($driver->unread());

        static::assertEmpty($emails);
    }

    public function test_extract_xml_branches(): void
    {
        $this->mock(GraphServiceClient::class, static function (MockInterface $mock): void {
            $userBuilder = Mockery::mock(UserItemRequestBuilder::class);
            $messagesBuilder = Mockery::mock(MessagesRequestBuilder::class);

            $mock->expects('me')->andReturn($userBuilder);
            $userBuilder->expects('messages')->andReturn($messagesBuilder);

            $message1 = new Message;
            $message1->setAttachments(null);

            $message2 = new Message;
            $itemAttachment = new ItemAttachment;
            $message2->setAttachments([$itemAttachment]);

            $message3 = new Message;
            $fileAttachment = new FileAttachment;
            $fileAttachment->setContentType('text/plain');
            $message3->setAttachments([$fileAttachment]);

            $message4 = new Message;
            $fileAttachment2 = new FileAttachment;
            $fileAttachment2->setContentType('application/xml');
            $fileAttachment2->setContentBytes(Utils::streamFor(base64_encode('xml content')));
            $message4->setAttachments([$fileAttachment2]);

            $message5 = new Message;
            $fileAttachment3 = new FileAttachment;
            $fileAttachment3->setContentType('text/xml');
            $fileAttachment3->setContentBytes(Utils::streamFor(base64_encode('xml content 2')));
            $message5->setAttachments([$fileAttachment3]);

            $messagesResponse = Mockery::mock();
            $messagesResponse->expects('getValue')->andReturn([$message1, $message2, $message3, $message4, $message5]);

            $promise = Mockery::mock(Promise::class);
            $promise->expects('wait')->andReturn($messagesResponse);

            $messagesBuilder->expects('get')->andReturn($promise);
        });

        $driver = $this->makeDriver();
        $emails = iterator_to_array($driver->unread());

        static::assertEquals('', $emails[0]->xmlAttachment);
        static::assertEquals('', $emails[1]->xmlAttachment);
        static::assertEquals('', $emails[2]->xmlAttachment);
        static::assertEquals('xml content', $emails[3]->xmlAttachment);
        static::assertEquals('xml content 2', $emails[4]->xmlAttachment);
    }
}
