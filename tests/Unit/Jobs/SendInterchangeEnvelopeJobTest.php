<?php

namespace Tests\Unit\Jobs;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Mail;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Contracts\TokenProvider;
use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Jobs\SendInterchangeEnvelopeJob;
use Laragear\Dte\Mail\Interchange\InterchangeEnvelopeMail;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDteEnvelopePayload;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use Tests\DatabaseTestCase as TestCase;

class SendInterchangeEnvelopeJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_splits_dtes_by_receiver_and_dispatches_mail(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();

        $dte1 = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id,
            'receiver_rut' => '76123456-0',
            'document_type' => DteType::Invoice,
        ]);
        $dte2 = SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id,
            'receiver_rut' => '77123456-9',
            'document_type' => DteType::Invoice,
        ]);

        // Inject cache so the resolver returns test@example.com
        $this->app->make(CacheFactory::class)->put('dte|exchange_email|rut:761234560', 'test@example.com');
        $this->app->make(CacheFactory::class)->put('dte|exchange_email|rut:771234569', 'test@example.com');

        $this->mock(TokenProvider::class)
            ->expects('token')
            ->zeroOrMoreTimes()
            ->andReturn(new Token('test', time() + 3600));

        $this->mock(CreateEnvelope::class, function (MockInterface $mock) {
            $mock->expects('forSharing')->times(2)->andReturn(
                SiiDteEnvelope::factory()->make()->setRelation('payload',
                    SiiDteEnvelopePayload::factory()->make(['xml' => '<xml>1</xml>'])),
                SiiDteEnvelope::factory()->make()->setRelation('payload',
                    SiiDteEnvelopePayload::factory()->make(['xml' => '<xml>2</xml>'])),
            );
        });

        Mail::fake();

        $job = new SendInterchangeEnvelopeJob($envelope);

        $this->app->call($job->handle(...));

        Mail::assertSent(InterchangeEnvelopeMail::class, 2);
        Mail::assertSent(InterchangeEnvelopeMail::class, function (InterchangeEnvelopeMail $mail): bool {
            return $mail->hasTo('test@example.com') && $mail->xml !== '';
        });
    }

    public function test_skips_when_dim_email_not_resolved(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'receiver_rut' => '76123456-0', 'document_type' => DteType::Invoice,
        ]);

        // Don't cache anything, testing environment returns null
        $this->mock(TokenProvider::class)->expects('token')
            ->zeroOrMoreTimes()->andReturn(new Token('test',
                time() + 3600));
        $this->mock(CreateEnvelope::class)->expects('forSharing')->never();

        Mail::fake();

        $this->mock(LoggerInterface::class)->expects('warning');

        $job = new SendInterchangeEnvelopeJob($envelope);
        $this->app->call($job->handle(...));

        Mail::assertNothingSent();
    }

    public function test_gracefully_skips_envelopes_containing_only_boletas(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'receiver_rut' => '76123456-0', 'document_type' => 39,
        ]); // Boleta

        $this->mock(TokenProvider::class)->expects('token')
            ->zeroOrMoreTimes()->andReturn(new Token('test',
                time() + 3600));
        $this->mock(CreateEnvelope::class);

        Mail::fake();

        $job = new SendInterchangeEnvelopeJob($envelope);
        $this->app->call($job->handle(...));

        Mail::assertNothingSent();
    }

    public function test_aborts_when_creator_fails_compilation(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'receiver_rut' => '76123456-0', 'document_type' => DteType::Invoice,
        ]);

        $this->app->make(CacheFactory::class)->put('dte|exchange_email|rut:761234560',
            'test@example.com');
        $this->mock(TokenProvider::class)->expects('token')
            ->zeroOrMoreTimes()->andReturn(new Token('test',
                time() + 3600));
        $this->mock(CreateEnvelope::class)->expects('forSharing')->andThrow(new RuntimeException('Compilation Failed'));

        Mail::fake();

        $this->mock(LoggerInterface::class)->expects('error');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Compilation Failed');

        $job = new SendInterchangeEnvelopeJob($envelope);
        $this->app->call($job->handle(...));
    }

    public function test_aborts_when_mail_sending_throws_exception(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'receiver_rut' => '76123456-0', 'document_type' => DteType::Invoice,
        ]);

        $this->app->make(CacheFactory::class)->put('dte|exchange_email|rut:761234560',
            'test@example.com');
        $this->mock(TokenProvider::class)->expects('token')
            ->zeroOrMoreTimes()->andReturn(new Token('test',
                time() + 3600));
        $this->mock(CreateEnvelope::class, function (MockInterface $mock) {
            $mock->expects('forSharing')->andReturn(
                SiiDteEnvelope::factory()->make()->setRelation('payload',
                    SiiDteEnvelopePayload::factory()->make(['xml' => '<xml>3</xml>'])),
            );
        });

        $pendingMail = $this->mock(PendingMail::class, static function (MockInterface $mock): void {
            $mock->expects('send')->andThrow(new RuntimeException('Network error'));
        });

        $mailer = $this->mock(Mailer::class, static function (MockInterface $mock) use ($pendingMail): void {
            $mock->expects('to')->andReturn($pendingMail);
        });

        $factory = $this->mock(Factory::class, static function (MockInterface $mock) use ($mailer): void {
            $mock->expects('mailer')->andReturn($mailer);
        });

        $this->app->instance(Factory::class, $factory);

        $this->mock(LoggerInterface::class)->expects('error');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Network error');

        $job = new SendInterchangeEnvelopeJob($envelope);
        $this->app->call($job->handle(...));
    }

    public function test_aborts_when_envelope_xml_is_empty(): void
    {
        $envelope = SiiDteEnvelope::factory()->create();
        SiiDte::factory()->create([
            'sii_dte_envelope_id' => $envelope->id, 'receiver_rut' => '76123456-0', 'document_type' => DteType::Invoice,
        ]);

        $this->app->make(CacheFactory::class)->put('dte|exchange_email|rut:761234560',
            'test@example.com');
        $this->mock(TokenProvider::class)
            ->expects('token')
            ->zeroOrMoreTimes()
            ->andReturn(new Token('test', time() + 3600));

        $this->mock(CreateEnvelope::class, function (MockInterface $mock) {
            $mock->expects('forSharing')->andReturn(
                SiiDteEnvelope::factory()->make()->setRelation('payload',
                    SiiDteEnvelopePayload::factory()->make(['xml' => ''])),
            );
        });

        Mail::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Failed to cast ephemeral envelope to XML string.');

        $job = new SendInterchangeEnvelopeJob($envelope);
        $this->app->call($job->handle(...));
    }

    public function test_job_has_tries_backoff_and_timeout_attributes(): void
    {
        $reflection = new ReflectionClass(SendInterchangeEnvelopeJob::class);

        $backoff = $reflection->getAttributes(Backoff::class)[0]->newInstance()->backoff;
        $tries = $reflection->getAttributes(Tries::class)[0]->newInstance()->tries;
        $timeout = $reflection->getAttributes(Timeout::class)[0]->newInstance()->timeout;

        static::assertSame([60, 120, 300], $backoff);
        static::assertSame(3, $tries);
        static::assertSame(120, $timeout);
    }
}
