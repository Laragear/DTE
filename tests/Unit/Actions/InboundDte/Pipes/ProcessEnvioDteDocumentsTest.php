<?php

namespace Tests\Unit\Actions\InboundDte\Pipes;

use Illuminate\Support\Facades\Event;
use Laragear\Dte\Actions\InboundDte\InboundDteData;
use Laragear\Dte\Actions\InboundDte\Pipes\ProcessEnvioDteDocuments;
use Laragear\Dte\Actions\InboundDte\ProcessInboundDte;
use Laragear\Dte\Contracts\TenantResolver;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Events\InboundDteReceived;
use Laragear\Dte\Events\InboundForgedDteReceived;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Models\SiiInterchangeLog;
use Laragear\Dte\Services\DteAuthenticityVerifier;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use RuntimeException;
use Tests\DatabaseTestCase;

class ProcessEnvioDteDocumentsTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    /*
    |--------------------------------------------------------------------------
    | Happy paths
    |--------------------------------------------------------------------------
    */

    public function test_skips_non_envio_dte(): void
    {
        $this->mock(TenantResolver::class);

        $data = new InboundDteData(
            new InboundEmailData('msg-1', 'a@b.cl', 'Subject', '<RespuestaDTE></RespuestaDTE>'),
            rootName: 'RespuestaDTE',
        );

        $data->log = SiiInterchangeLog::factory()->create();

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ProcessEnvioDteDocuments::class)
            ->send($data)
            ->assertPassable(function (InboundDteData $result) {
                return $result->rootName === 'RespuestaDTE';
            });
    }

    public function test_persists_document_and_dispatches_received_event(): void
    {
        $event = Event::fake([InboundDteReceived::class, InboundForgedDteReceived::class]);

        $xmlString = static::getStub('EnvioDteSingleDocument.xml');

        $tenant = (object) ['id' => 1];

        $this->mock(TenantResolver::class, static function ($mock) use ($tenant) {
            $mock->expects('resolve')->andReturn($tenant);
        });

        $this->mock(DteAuthenticityVerifier::class, static function ($mock) {
            $mock->expects('verify')->andReturn(true);
        });

        $data = new InboundDteData(
            new InboundEmailData('msg-1', 'a@b.cl', 'Subject', $xmlString),
            xml: $this->app->make(XmlDomFactory::class)->simpleXml($xmlString),
            rootName: 'EnvioDTE',
        );

        $data->log = SiiInterchangeLog::factory()->create();

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ProcessEnvioDteDocuments::class)
            ->send($data)
            ->assertPassable(function (InboundDteData $result) use ($event, $data) {
                $document = SiiInboundDocument::where('sii_interchange_log_id', $data->log->id)->first();

                static::assertNotNull($document);
                static::assertSame(InboundDteStatus::Received, $document->status);
                static::assertNotNull($document->payload);
                $event->assertDispatched(InboundDteReceived::class);

                return true;
            });
    }

    public function test_persists_document_and_dispatches_forged_event(): void
    {
        $event = Event::fake([InboundDteReceived::class, InboundForgedDteReceived::class]);

        $xmlString = static::getStub('EnvioDteSingleDocument.xml');

        $tenant = (object) ['id' => 1];

        $this->mock(TenantResolver::class, static function ($mock) use ($tenant) {
            $mock->expects('resolve')->andReturn($tenant);
        });

        $this->mock(DteAuthenticityVerifier::class, static function ($mock) {
            $mock->expects('verify')->andReturn(false);
        });

        $data = new InboundDteData(
            new InboundEmailData('msg-1', 'a@b.cl', 'Subject', $xmlString),
            xml: $this->app->make(XmlDomFactory::class)->simpleXml($xmlString),
            rootName: 'EnvioDTE',
        );

        $data->log = SiiInterchangeLog::factory()->create();

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ProcessEnvioDteDocuments::class)
            ->send($data)
            ->assertPassable(function (InboundDteData $result) use ($event, $data) {
                $document = SiiInboundDocument::where('sii_interchange_log_id', $data->log->id)->first();

                static::assertNotNull($document);
                static::assertSame(InboundDteStatus::Forged, $document->status);
                $event->assertDispatched(InboundForgedDteReceived::class);

                return true;
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Phantom adoption
    |--------------------------------------------------------------------------
    */

    public function test_adopts_existing_phantom_instead_of_colliding(): void
    {
        Event::fake([InboundDteReceived::class, InboundForgedDteReceived::class]);

        $xmlString = static::getStub('EnvioDteSingleDocument.xml');

        $tenant = (object) ['id' => 1];

        $this->mock(TenantResolver::class, static function ($mock) use ($tenant) {
            $mock->expects('resolve')->andReturn($tenant);
        });

        $this->mock(DteAuthenticityVerifier::class, static function ($mock) {
            $mock->expects('verify')->andReturn(true);
        });

        $phantom = SiiInboundDocument::factory()->create([
            'issuer_rut' => '11111111-1',
            'receiver_rut' => '76123456-0',
            'document_type' => 33,
            'folio' => 1,
            'amount_total' => 50000,
            'status' => InboundDteStatus::PhantomPending,
        ]);

        $data = new InboundDteData(
            new InboundEmailData('msg-1', 'a@b.cl', 'Subject', $xmlString),
            xml: $this->app->make(XmlDomFactory::class)->simpleXml($xmlString),
            rootName: 'EnvioDTE',
        );

        $data->log = SiiInterchangeLog::factory()->create();

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ProcessEnvioDteDocuments::class)
            ->send($data)
            ->assertPassable(function (InboundDteData $result) use ($phantom) {
                // The phantom row was adopted, not duplicated.
                static::assertSame(1, SiiInboundDocument::query()->count());

                $phantom->refresh();

                static::assertSame(InboundDteStatus::Received, $phantom->status);
                static::assertNotNull($phantom->payload);
                static::assertSame(11900, $phantom->amount_total);

                return true;
            });
    }

    public function test_re_delivered_xml_never_regresses_status_or_duplicates_payload(): void
    {
        Event::fake([InboundDteReceived::class, InboundForgedDteReceived::class]);

        $xmlString = static::getStub('EnvioDteSingleDocument.xml');

        $tenant = (object) ['id' => 1];

        $this->mock(TenantResolver::class, static function ($mock) use ($tenant) {
            $mock->allows('resolve')->andReturn($tenant);
        });

        $this->mock(DteAuthenticityVerifier::class, static function ($mock) {
            $mock->allows('verify')->andReturn(true);
        });

        $data = new InboundDteData(
            new InboundEmailData('msg-1', 'a@b.cl', 'Subject', $xmlString),
            xml: $this->app->make(XmlDomFactory::class)->simpleXml($xmlString),
            rootName: 'EnvioDTE',
        );

        $data->log = SiiInterchangeLog::factory()->create();

        foreach (range(1, 2) as $run) {
            $this
                ->pipeline(ProcessInboundDte::class)
                ->isolatePipe(ProcessEnvioDteDocuments::class)
                ->send($data);
        }

        static::assertSame(1, SiiInboundDocument::query()->count());

        $document = SiiInboundDocument::query()->first();

        static::assertSame(InboundDteStatus::Received, $document->status);
        static::assertSame(1, $document->payload()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Sad paths
    |--------------------------------------------------------------------------
    */

    public function test_throws_when_rut_receptor_missing(): void
    {
        $xmlString = '<EnvioDTE><SetDTE><Caratula></Caratula></SetDTE></EnvioDTE>';

        $this->mock(TenantResolver::class);

        $data = new InboundDteData(
            new InboundEmailData('msg-1', 'a@b.cl', 'Subject', $xmlString),
            xml: $this->app->make(XmlDomFactory::class)->simpleXml($xmlString),
            rootName: 'EnvioDTE',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Missing RutReceptor in EnvioDTE.');

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ProcessEnvioDteDocuments::class)
            ->send($data)
            ->assertPassable(fn () => false);
    }

    public function test_throws_when_tenant_not_found(): void
    {
        $xmlString = static::getStub('EnvioDteSingleDocument.xml');

        $this->mock(TenantResolver::class, static function ($mock) {
            $mock->expects('resolve')->andReturnNull();
        });

        $data = new InboundDteData(
            new InboundEmailData('msg-1', 'a@b.cl', 'Subject', $xmlString),
            xml: $this->app->make(XmlDomFactory::class)->simpleXml($xmlString),
            rootName: 'EnvioDTE',
        );

        $data->log = SiiInterchangeLog::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Tenant for RUT 76123456-0 not found.');

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ProcessEnvioDteDocuments::class)
            ->send($data)
            ->assertPassable(fn () => false);
    }
}
