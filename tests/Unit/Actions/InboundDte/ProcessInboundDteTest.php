<?php

namespace Tests\Unit\Actions\InboundDte;

use Closure;
use Laragear\Dte\Actions\InboundDte\InboundDteData;
use Laragear\Dte\Actions\InboundDte\Pipes\CreateInterchangeLog;
use Laragear\Dte\Actions\InboundDte\Pipes\ParseInboundDocument;
use Laragear\Dte\Actions\InboundDte\Pipes\ProcessEnvioDteDocuments;
use Laragear\Dte\Actions\InboundDte\Pipes\ProcessRespuestaDteDocuments;
use Laragear\Dte\Actions\InboundDte\Pipes\ValidateXmlStructure;
use Laragear\Dte\Actions\InboundDte\ProcessInboundDte;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\DatabaseTestCase;

class ProcessInboundDteTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    public function test_pipeline_order(): void
    {
        $this->pipeline(ProcessInboundDte::class)
            ->assertPipes([
                ValidateXmlStructure::class,
                ParseInboundDocument::class,
                CreateInterchangeLog::class,
                ProcessEnvioDteDocuments::class,
                ProcessRespuestaDteDocuments::class,
            ]);
    }

    public function test_receives_rut_into_passable(): void
    {
        $pipeline = $this->app->make(ProcessInboundDte::class);

        $pipeline->through([]);

        $email = new InboundEmailData('test', 'test', 'test', 'text-xml');

        $result = $pipeline->forEmail($email);

        static::assertEquals($email, $result->email);
    }

    public function test_failure_logs_and_rolls_back(): void
    {
        $this
            ->mock(LoggerInterface::class)
            ->shouldReceive('error')
            ->once()
            ->with(
                'Inbound DTE email processing failed, rolling back the email unit.',
                Mockery::on(static fn(array $context): bool => $context['flow'] === 'inbound-email'
                    && $context['message_id'] === 'msg-1'
                    && $context['sender'] === 'sender@example.com'
                    && $context['exception'] === RuntimeException::class),
            );

        $pipeline = $this->app->make(ProcessInboundDte::class);
        $pipeline->through([Closure::class.'@handle']);

        $this->app->bind(Closure::class.'@handle', function () {
            return function (InboundDteData $data) {
                throw new RuntimeException('Inbound failure.');
            };
        });

        try {
            $pipeline->forEmail(new InboundEmailData('msg-1', 'sender@example.com', 'Subject', '<xml/>'));

            static::fail('Expected inbound processing to fail.');
        } catch (RuntimeException $e) {
            static::assertSame('Inbound failure.', $e->getMessage());
        }

        static::assertDatabaseCount('sii_interchange_logs', 0);
    }
}
