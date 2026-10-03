<?php

namespace Tests\Unit\Services;

use DateTimeImmutable;
use Laragear\Dte\Builders\CommercialReceiptBuilder;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Gateways\ReclamoWebserviceGateway;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Services\DteClaimService;
use Laragear\Rut\Rut;
use LogicException;
use Mockery\MockInterface;
use RuntimeException;
use Tests\DatabaseTestCase;

class DteClaimServiceTest extends DatabaseTestCase
{
    public function test_accepts_invoice(): void
    {
        $document = SiiInboundDocument::factory()->create();
        $signer = Rut::parse('1-9');
        $location = 'Test location';
        $certificate = new DigitalCertificate('fake', 'fake');
        $signedAt = new DateTimeImmutable;

        $this->mock(ReclamoWebserviceGateway::class)->expects('accept')->with($document);

        $this
            ->mock(CommercialReceiptBuilder::class)
            ->expects('build')
            ->with($document, $signer, $location, $certificate, $signedAt)
            ->andReturn('<xml>erm</xml>');

        $service = $this->app->make(DteClaimService::class);

        $result = $service->accept($document, $signer, $location, $certificate, $signedAt);

        static::assertSame('<xml>erm</xml>', $result);
    }

    public function test_rejects_invoice(): void
    {
        $document = SiiInboundDocument::factory()->create();

        $this->mock(ReclamoWebserviceGateway::class)->expects('reject')->with($document, 'test reason');

        $service = $this->app->make(DteClaimService::class);

        $service->reject($document, 'test reason');
    }

    public function test_rejects_invoice_due_to_missing_goods(): void
    {
        $document = SiiInboundDocument::factory()->create();

        $this->mock(ReclamoWebserviceGateway::class)->expects('rejectGoods')->with($document, 'test reason');

        $service = $this->app->make(DteClaimService::class);

        $service->rejectGoods($document, 'test reason');
    }

    public function test_throws_when_document_already_claimed(): void
    {
        $document = SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::CommercialAccepted,
        ]);

        $service = $this->app->make(DteClaimService::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The document has already been commercially claimed or accepted.');

        $service->reject($document, 'reason');
    }

    public function test_throws_when_claim_is_lost_to_concurrent_operation(): void
    {
        $document = SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::CommercialAccepted,
        ]);

        // Stale in-memory state passes the guard, but the conditional UPDATE
        // finds the already-claimed row, as if a concurrent run won the race.
        $document->forceFill(['status' => InboundDteStatus::PhantomPending]);

        $this->mock(ReclamoWebserviceGateway::class, static function (MockInterface $mock): void {
            $mock->expects('reject')->never();
        });

        $service = $this->app->make(DteClaimService::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('has already been commercially claimed or accepted.');

        $service->reject($document, 'reason');
    }

    public function test_accept_timeout_or_error(): void
    {
        $document = SiiInboundDocument::factory()->create();
        $signer = Rut::parse('1-9');
        $location = 'Test location';
        $certificate = new DigitalCertificate('fake', 'fake');
        $signedAt = new DateTimeImmutable;

        $this
            ->mock(ReclamoWebserviceGateway::class)
            ->expects('accept')
            ->with($document)
            ->andThrow(new RuntimeException('SII timeout'));

        $service = $this->app->make(DteClaimService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('SII timeout');

        $service->accept($document, $signer, $location, $certificate, $signedAt);
    }

    public function test_rejects_for_partial_missing_goods(): void
    {
        $document = SiiInboundDocument::factory()->create();

        $this->mock(ReclamoWebserviceGateway::class)->expects('rejectPartial')->with($document, 'partial reason');

        $service = $this->app->make(DteClaimService::class);

        $service->rejectPartial($document, 'partial reason');
    }

    public function test_confirms_goods_receipt(): void
    {
        $document = SiiInboundDocument::factory()->create();

        $this->mock(ReclamoWebserviceGateway::class)->expects('confirmGoodsReceipt')->with($document, 'receipt reason');

        $service = $this->app->make(DteClaimService::class);

        $service->confirmGoodsReceipt($document, 'receipt reason');
    }

    public function test_throws_when_already_rejected_for_confirm(): void
    {
        $document = SiiInboundDocument::factory()->create([
            'status' => InboundDteStatus::CommercialRejected,
        ]);

        $service = $this->app->make(DteClaimService::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The document has already been commercially claimed or accepted.');

        $service->confirmGoodsReceipt($document);
    }
}
