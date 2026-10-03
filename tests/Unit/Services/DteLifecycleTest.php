<?php

namespace Tests\Unit\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\Queue;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Actions\PersistDte\PersistDte;
use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelopePayload;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Dte\Services\DteLifecycleService;
use Laragear\Dte\Services\PackDtesService;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use Override;
use Psr\Log\LoggerInterface;
use Tests\DatabaseTestCase;
use Tests\Unit\Builders\Fixtures\BuilderFixture;

class DteLifecycleTest extends DatabaseTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConfigurationManager::setCompany(fn() => CompanyData::make(
            IssuerData::make(
                '76.123.456-0',
                'Test Company',
                'Software',
                ['620100'],
                'Test Address 123',
                'Santiago',
                '2025-01-01',
                76000,
                'Santiago',
                '+56212345678',
                'test@example.com',
                'Casa Matriz',
            ),
            '76.123.456-0',
        ));
    }

    protected function lifecycle(): DteLifecycleService
    {
        return $this->app->make(DteLifecycleService::class);
    }

    protected function newBuilder(): InvoiceBuilder
    {
        return $this->app->make(InvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver())
            ->addItem(BuilderFixture::item());
    }

    /*
    |--------------------------------------------------------------------------
    | Persistence
    |--------------------------------------------------------------------------
    */

    public function test_persist_dispatches_creating_and_created_events(): void
    {
        $events = [];
        $this->app->make('events')->listen('*', function (string $event) use (&$events): void {
            $events[] = $event;
        });

        $dte = $this->lifecycle()->persist($this->newBuilder(), false);

        static::assertContains('Laragear\Dte\Events\DteCreating', $events);
        static::assertContains('Laragear\Dte\Events\DteCreated', $events);
        static::assertTrue($dte->exists);
    }

    public function test_persist_throws_when_rebuilding_without_hydration(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Cannot build a document builder that has not been hydrated.');

        $this->lifecycle()->persist($this->newBuilder(), true);
    }

    public function test_persist_as_draft_throws_on_immutable_document(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Pending,
        ]);

        $builder = $this->app->make(InvoiceBuilder::class)->hydrate($dte);

        // draft() on a hydrated non-draft row must refuse to unlock it.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Only a draft DTE can be persisted as draft.');

        $builder->draft();
    }

    /*
    |--------------------------------------------------------------------------
    | Compilation
    |--------------------------------------------------------------------------
    */

    public function test_compile_queues_compilation_for_pending(): void
    {
        $queue = Queue::fake();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Pending,
        ]);

        $result = $this->lifecycle()->compile($dte);

        static::assertTrue($result->is($dte));
        $queue->assertPushed(QueuedCommand::class, 1);
    }

    public function test_compile_promotes_draft_to_pending_and_queues(): void
    {
        $queue = Queue::fake();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Draft,
        ]);

        $this->lifecycle()->compile($dte);

        static::assertSame(DteStatus::Pending, $dte->refresh()->status);
        $queue->assertPushed(QueuedCommand::class, 1);
    }

    public function test_compile_returns_compiled_without_dispatching(): void
    {
        $queue = Queue::fake();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Outbox,
        ]);

        $this->mock(Compile::class, function (MockInterface $mock): void {
            $mock->expects('forDte')->never();
        });

        $result = $this->lifecycle()->compile($dte);

        static::assertTrue($result->is($dte));
        $queue->assertNothingPushed();
    }

    public function test_compile_sync_runs_inline(): void
    {
        $queue = Queue::fake();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Pending,
        ]);

        $this->mock(Compile::class, function (MockInterface $mock) use ($dte): void {
            $mock->expects('forDte')->andReturnUsing(static function (SiiDte $sent) use ($dte): SiiDte {
                static::assertTrue($sent->is($dte));

                return $sent;
            });
        });

        $result = $this->lifecycle()->compile($dte, true);

        static::assertTrue($result->is($dte));
        $queue->assertNothingPushed();
    }

    /*
    |--------------------------------------------------------------------------
    | Send
    |--------------------------------------------------------------------------
    */

    public function test_send_throws_for_draft(): void
    {
        $dte = SiiDte::factory()->create(['status' => DteStatus::Draft]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Build it with build() first.');

        $this->lifecycle()->send($dte);
    }

    public function test_second_send_reuses_no_envelope_and_throws(): void
    {
        Queue::fake();

        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Outbox,
        ]);

        $first = $this->lifecycle()->send($dte);

        static::assertSame(DteStatus::Packed, $dte->refresh()->status);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains("already belongs to envelope [{$first->getKey()}].");

        $this->lifecycle()->send($dte->refresh());
    }

    public function test_send_sync_processes_envelope_inline(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Outbox,
        ]);

        $this->mock(CreateEnvelope::class, function (MockInterface $mock): void {
            $mock->expects('forEnvelope')->andReturnUsing(function ($envelope) {
                $envelope->setRelation('payload', SiiDteEnvelopePayload::factory()->make(['xml' => 'signed-xml']));

                return $envelope;
            });
        });

        $envelope = $this->lifecycle()->send($dte, true);

        static::assertSame(DteStatus::Sent, $dte->refresh()->status);
        static::assertNotNull($envelope->track_id);
    }

    public function test_send_aborts_when_concurrent_send_claims_first(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Pending,
        ]);

        $this->mock(Compile::class, function (MockInterface $mock): void {
            $mock->expects('forDte')->andReturnUsing(function (SiiDte $sent): SiiDte {
                // A concurrent send wins the race before our claim runs.
                $sent->forceFill(['status' => DteStatus::Outbox, 'sii_dte_envelope_id' => 999])->save();

                return $sent;
            });
        });

        $this
            ->mock(LoggerInterface::class)
            ->shouldReceive('error')
            ->once()
            ->with(
                'Exclusive envelope creation failed, rolling back to the pre-send state.',
                Mockery::on(static fn(array $context): bool => $context['flow'] === 'dte-send'
                    && $context['dte_id'] === $dte->getKey()),
            );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('already belongs to envelope [999].');

        $this->lifecycle()->send($dte);
    }

    public function test_send_throws_when_dte_is_not_compiled_at_claim(): void
    {
        $dte = SiiDte::factory()->has(SiiDtePayload::factory(), 'payload')->create([
            'status' => DteStatus::Pending,
        ]);

        $service = new class(
            $this->app->make(Kernel::class),
            $this->app->make(Repository::class),
            tap($this->mock(LoggerInterface::class), static function ($log) use ($dte): void {
                $log->shouldReceive('error')->once()->with(
                    'Exclusive envelope creation failed, rolling back to the pre-send state.',
                    Mockery::on(static fn(array $context): bool => $context['flow'] === 'dte-send'
                        && $context['dte_id'] === $dte->getKey()),
                );
            }),
            $this->app->make(Dispatcher::class),
            $this->app->make(PersistDte::class),
            $this->app->make(Compile::class),
            $this->app->make(PackDtesService::class),
        ) extends DteLifecycleService {
            protected function compileIfNecessary(SiiDte $dte): void
            {
                //
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('is not compiled and cannot be sent.');

        $service->send($dte);
    }
}
