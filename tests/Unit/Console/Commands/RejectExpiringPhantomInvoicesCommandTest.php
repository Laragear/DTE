<?php

namespace Tests\Unit\Console\Commands;

use Laragear\Dte\Data\Token;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Gateways\ReclamoWebserviceGateway;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Proxies\SoapProxy;
use Laragear\Dte\Support\TokenAuthenticator;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SoapClient;
use Tests\DatabaseTestCase;

use function now;

class RejectExpiringPhantomInvoicesCommandTest extends DatabaseTestCase
{
    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_continues_on_failure_and_logs_error(): void
    {
        // Faking config and tokens
        $this->app['env'] = 'production';
        $this->config('app.env', 'production');
        $this->config('dte.environment', 'production');
        $this->app->make(EnvironmentResolver::class)->flush();

        $expiredDoc1 = SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::PhantomPending,
            'created_at' => now()->subDays(7),
        ]);

        $expiredDoc2 = SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::PhantomPending,
            'created_at' => now()->subDays(7),
        ]);

        $this
            ->mock(LoggerInterface::class)
            ->expects('error')
            ->withArgs(static function (string $message, array $context) use ($expiredDoc1): bool {
                static::assertSame('Failed to reject phantom invoice.', $message);
                static::assertSame($expiredDoc1->id, $context['document_id']);
                static::assertSame('API Error', $context['error']);
                static::assertIsArray($context['trace']);

                return true;
            });

        $client = $this->mock(SoapClient::class, static function (MockInterface $mock) use (
            $expiredDoc1,
            $expiredDoc2,
        ): void {
            $mock->expects('__setSoapHeaders')->twice();

            $mock
                ->expects('__soapCall')
                ->with('ReclamoDoc', Mockery::on(function ($args) use ($expiredDoc1) {
                    return $args[0]['RutEmisor'] === $expiredDoc1->issuer_rut->num;
                }))
                ->andThrow(new RuntimeException('API Error'));

            $mock
                ->expects('__soapCall')
                ->with('ReclamoDoc', Mockery::on(function ($args) use ($expiredDoc2) {
                    return $args[0]['RutEmisor'] === $expiredDoc2->issuer_rut->num;
                }))
                ->andReturn((object) ['ReclamoDocResult' => (object) ['status' => 0]]);
        });

        $this->mock(SoapProxy::class, static function (MockInterface $mock) use ($client): void {
            $mock->expects('withWsdl')->twice()->andReturnSelf();
            $mock->expects('build')->twice()->andReturn($client);
        });

        $this
            ->mock(TokenAuthenticator::class, static function (MockInterface $mock): void {
                $mock->expects('token')->twice()->andReturn(new Token('fake', time() + 3600));
                $mock->expects('retryWithFreshToken')->twice()->andReturnUsing(fn ($request, $issuer) => $request());
            });

        $this
            ->artisan('dte:reject-phantom-invoices')
            ->expectsOutput('Rejected 1 phantom invoices. Failed: 1.')
            ->assertSuccessful();

        $this->assertDatabaseHas(SiiInboundDocument::class, [
            'id' => $expiredDoc1->id,
            'status' => InboundDteStatus::PhantomPending,
        ]);

        $this->assertDatabaseHas(SiiInboundDocument::class, [
            'id' => $expiredDoc2->id,
            'status' => InboundDteStatus::CommercialRejected,
        ]);
    }

    public function test_outputs_info_if_no_documents_found(): void
    {
        $this
            ->artisan('dte:reject-phantom-invoices')
            ->expectsOutput('No expiring phantom invoices found.')
            ->assertSuccessful();
    }

    public function test_rejects_phantom_invoices_older_than_threshold(): void
    {
        // Faking config and tokens
        $this->app['env'] = 'production';
        $this->config('app.env', 'production');
        $this->config('dte.environment', 'production');
        $this->app->make(EnvironmentResolver::class)->flush();

        // Created 7 days ago
        $expiredDoc = SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::PhantomPending,
            'created_at' => now()->subDays(7),
        ]);

        // Created 5 days ago (should not be processed if threshold is 6)
        $newDoc = SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::PhantomPending,
            'created_at' => now()->subDays(5),
        ]);

        $client = $this->mock(SoapClient::class, static function (MockInterface $mock) use ($expiredDoc): void {
            $mock->expects('__setSoapHeaders');
            $mock
                ->expects('__soapCall')
                ->with('ReclamoDoc', Mockery::on(function ($args) use ($expiredDoc) {
                    return $args[0]['RutEmisor'] === $expiredDoc->issuer_rut->num && $args[0]['AccionDoc'] === 'RCD';
                }))
                ->andReturn((object) ['ReclamoDocResult' => (object) ['status' => 0]]);
        });

        $this->mock(SoapProxy::class, static function (MockInterface $mock) use ($client): void {
            $mock->expects('withWsdl')->andReturnSelf();
            $mock->expects('build')->andReturn($client);
        });

        $this
            ->mock(TokenAuthenticator::class, static function (MockInterface $mock): void {
                $mock->expects('token')->andReturn(new Token('fake', time() + 3600));
                $mock->expects('retryWithFreshToken')->andReturnUsing(fn ($request, $issuer) => $request());
            });

        $this
            ->artisan('dte:reject-phantom-invoices')
            ->expectsOutput('Rejected 1 phantom invoices. Failed: 0.')
            ->assertSuccessful();

        $this->assertDatabaseHas(SiiInboundDocument::class, [
            'id' => $expiredDoc->id,
            'status' => InboundDteStatus::CommercialRejected,
        ]);

        $this->assertDatabaseHas(SiiInboundDocument::class, [
            'id' => $newDoc->id,
            'status' => InboundDteStatus::PhantomPending,
        ]);
    }

    public function test_skips_document_claimed_by_concurrent_operation(): void
    {
        $doc1 = SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::PhantomPending,
            'created_at' => now()->subDays(7),
        ]);

        $doc2 = SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::PhantomPending,
            'created_at' => now()->subDays(7),
        ]);

        $this
            ->mock(LoggerInterface::class)
            ->expects('error')
            ->once()
            ->withArgs(static function (string $message, array $context) use ($doc1, $doc2): bool {
                static::assertSame('Failed to reject phantom invoice.', $message);
                static::assertContains($context['document_id'], [$doc1->id, $doc2->id]);
                static::assertStringContainsString('already been commercially claimed', $context['error']);

                return true;
            });

        $this->mock(ReclamoWebserviceGateway::class, static function (MockInterface $mock) use ($doc1, $doc2): void {
            $mock->expects('reject')->once()->andReturnUsing(
                static function (SiiInboundDocument $document) use ($doc1, $doc2): void {
                    // Simulate a concurrent run claiming the other document first.
                    $other = $document->is($doc1) ? $doc2 : $doc1;

                    SiiInboundDocument::query()->whereKey($other->getKey())
                        ->update(['status' => InboundDteStatus::CommercialRejected]);
                }
            );
        });

        $this
            ->artisan('dte:reject-phantom-invoices')
            ->expectsOutput('Rejected 1 phantom invoices. Failed: 1.')
            ->assertSuccessful();
    }

    public function test_anchors_deadline_on_reception_date_over_creation_date(): void
    {
        $this->app['env'] = 'production';
        $this->config('app.env', 'production');
        $this->config('dte.environment', 'production');
        $this->app->make(EnvironmentResolver::class)->flush();

        // Created long ago, but only received by the SII 7 days ago: claimable.
        $document = SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::PhantomPending,
            'created_at' => now()->subDays(20),
            'received_at' => now()->subDays(7),
        ]);

        $this->mock(ReclamoWebserviceGateway::class, static function (MockInterface $mock) use ($document): void {
            $mock->expects('reject')->withArgs(function (SiiInboundDocument $d, string $reason) use ($document): bool {
                return $d->is($document)
                    && $reason === 'Rechazo automático de factura fantasma (Sin recepción).';
            });
        });

        $this
            ->artisan('dte:reject-phantom-invoices')
            ->expectsOutput('Rejected 1 phantom invoices. Failed: 0.')
            ->assertSuccessful();

        $document->refresh();

        static::assertSame(InboundDteStatus::CommercialRejected, $document->status);
        static::assertSame('RCD', $document->claim_status);
    }

    public function test_skips_phantoms_past_the_legal_claim_window(): void
    {
        // Received 9 days ago: past the 8-day SII claim registration window.
        SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::PhantomPending,
            'created_at' => now()->subDays(9),
        ]);

        $this
            ->mock(LoggerInterface::class)
            ->expects('warning')
            ->once()
            ->with(
                'Skipping phantom invoice rejection: past the 8-day legal claim window.',
                Mockery::on(static fn (array $context): bool => $context['flow'] === 'phantom-reject'),
            );

        $this->mock(ReclamoWebserviceGateway::class, static function (MockInterface $mock): void {
            $mock->expects('reject')->never();
        });

        $this
            ->artisan('dte:reject-phantom-invoices')
            ->expectsOutput('Rejected 0 phantom invoices. Failed: 0.')
            ->assertSuccessful();
    }
}
