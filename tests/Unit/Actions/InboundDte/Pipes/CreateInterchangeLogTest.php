<?php

namespace Tests\Unit\Actions\InboundDte\Pipes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laragear\Dte\Actions\InboundDte\InboundDteData;
use Laragear\Dte\Actions\InboundDte\Pipes\CreateInterchangeLog;
use Laragear\Dte\Actions\InboundDte\ProcessInboundDte;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Models\SiiInterchangeLog;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Tests\DatabaseTestCase;

class CreateInterchangeLogTest extends DatabaseTestCase
{
    use InteractsWithPipelines;
    use RefreshDatabase;

    public function test_creates_log_and_sets_on_data(): void
    {
        $emailData = new InboundEmailData('msg-1', 'a@b.cl', 'Subject', '<xml></xml>');

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(CreateInterchangeLog::class)
            ->send(new InboundDteData($emailData))
            ->assertPassable(function (InboundDteData $result) {
                return $result->log !== null
                    && $result->log->message_id === 'msg-1'
                    && $result->log->sender === 'a@b.cl';
            });

        $this->assertDatabaseHas(SiiInterchangeLog::class, [
            'message_id' => 'msg-1',
            'sender' => 'a@b.cl',
            'subject' => 'Subject',
        ]);
    }
}
