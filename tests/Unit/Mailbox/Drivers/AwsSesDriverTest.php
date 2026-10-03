<?php

namespace Tests\Unit\Mailbox\Drivers;

use Aws\S3\S3Client;
use Aws\Ses\SesClient;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Mailbox\Drivers\AwsSesDriver;
use Mockery\MockInterface;
use Tests\TestCase;

class AwsSesDriverTest extends TestCase
{
    protected function makeDriver(array $config = []): AwsSesDriver
    {
        $this->config('dte.mailbox.batch_size', 50);
        $this->config('dte.mailbox.drivers.aws_ses', array_merge([
            'bucket' => 'test-bucket',
            'prefix' => 'emails/unread/',
            'read_prefix' => 'emails/read/',
            'region' => 'us-east-1',
            'key' => 'key',
            'secret' => 'secret',
            'from' => 'test@example.com',
        ], $config));

        return $this->app->make(AwsSesDriver::class);
    }

    public function test_yields_unread_emails_and_extracts_xml(): void
    {
        $this->mock(S3Client::class, static function (MockInterface $mock): void {
            $mock
                ->expects('listObjectsV2')
                ->with([
                    'Bucket' => 'test-bucket',
                    'Prefix' => 'emails/unread/',
                    'MaxKeys' => 50,
                ])
                ->andReturn([
                    'Contents' => [
                        ['Key' => 'emails/unread/msg1'],
                        ['Key' => 'emails/unread/msg2'],
                        ['Key' => 'emails/unread/msg3'],
                    ],
                ]);

            $mock
                ->expects('getObject')
                ->with(['Bucket' => 'test-bucket', 'Key' => 'emails/unread/msg1'])
                ->andReturn([
                    'Body' => "Message-ID: <msg1>\r\nFrom: sender@domain.com\r\nSubject: Test 1\r\n"
                        ."Content-Type: multipart/mixed; boundary=\"b1\"\r\n\r\n"
                        ."--b1\r\nContent-Type: text/plain\r\n\r\nHello\r\n"
                        ."--b1\r\nContent-Type: text/xml\r\nContent-Disposition: attachment; filename=\"envio.xml\"\r\n"
                        ."Content-Transfer-Encoding: base64\r\n\r\n"
                        .base64_encode('<?xml version="1.0"?><EnvioDTE></EnvioDTE>')
                        ."\r\n--b1--\r\n",
                ]);

            $mock
                ->expects('getObject')
                ->with(['Bucket' => 'test-bucket', 'Key' => 'emails/unread/msg2'])
                ->andReturn([
                    'Body' => "Message-ID: <msg2>\r\nFrom: sender2@domain.com\r\nSubject: Test 2\r\n"
                        ."Content-Type: multipart/mixed; boundary=\"b2\"\r\n\r\n"
                        ."--b2\r\nContent-Type: text/plain\r\n\r\nHello\r\n"
                        ."--b2\r\nContent-Type: application/xml\r\nContent-Disposition: attachment; filename=\"envio.xml\"\r\n"
                        ."Content-Transfer-Encoding: base64\r\n\r\n"
                        .base64_encode('<?xml version="1.0"?><EnvioDTE></EnvioDTE>')
                        ."\r\n--b2--\r\n",
                ]);

            $mock
                ->expects('getObject')
                ->with(['Bucket' => 'test-bucket', 'Key' => 'emails/unread/msg3'])
                ->andReturn(['Body' => "No valid headers\r\n\r\nEmpty body"]);
        });

        // We also need to bind SesClient to null if we want to mimic the old behaviour, or just mock it.
        $this->app->instance(SesClient::class, null);

        $driver = $this->makeDriver();

        $emails = iterator_to_array($driver->unread());

        static::assertCount(3, $emails);

        static::assertSame('<msg1>', $emails[0]->messageId);
        static::assertSame('sender@domain.com', $emails[0]->sender);
        static::assertSame('Test 1', $emails[0]->subject);
        static::assertStringContainsString('<?xml', $emails[0]->xmlAttachment);

        static::assertSame('<msg2>', $emails[1]->messageId);
        static::assertStringContainsString('<?xml', $emails[1]->xmlAttachment);

        static::assertSame('emails/unread/msg3', $emails[2]->messageId);
        static::assertSame('', $emails[2]->xmlAttachment);
    }

    public function test_marks_as_read(): void
    {
        $this->mock(S3Client::class, static function (MockInterface $mock): void {
            $mock
                ->expects('copyObject')
                ->with([
                    'Bucket' => 'test-bucket',
                    'CopySource' => 'test-bucket/emails/unread/msg1',
                    'Key' => 'emails/read/msg1',
                ]);

            $mock
                ->expects('deleteObject')
                ->with([
                    'Bucket' => 'test-bucket',
                    'Key' => 'emails/unread/msg1',
                ]);
        });

        $this->app->instance(SesClient::class, null);

        $driver = $this->makeDriver();

        $driver->markAsRead(new InboundEmailData('msg1', '', '', ''));
    }
}
