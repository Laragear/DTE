<?php

namespace Tests\Unit\Data;

use Laragear\Dte\Data\InboundEmailData;
use PHPUnit\Framework\TestCase;

class InboundEmailDataTest extends TestCase
{
    public function test_make_creates_instance(): void
    {
        $data = InboundEmailData::make('msg-1', 'sender@example.com', 'Subject', '<xml/>');

        static::assertSame('msg-1', $data->messageId);
        static::assertSame('sender@example.com', $data->sender);
        static::assertSame('Subject', $data->subject);
        static::assertSame('<xml/>', $data->xmlAttachment);
    }
}
