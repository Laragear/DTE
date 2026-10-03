<?php

namespace Laragear\Dte\Services;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Builders\CommercialReceiptBuilder;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Events\InboundDteAcknowledged;
use Laragear\Dte\Events\InboundDteAnswered;
use Laragear\Dte\Gateways\ReclamoWebserviceGateway;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Rut\Rut;
use LogicException;

class DteClaimService
{
    /**
     * Create a new Dte Claim Service instance.
     */
    public function __construct(
        protected Dispatcher $event,
        protected DateFactory $date,
        protected ReclamoWebserviceGateway $gateway,
        protected CommercialReceiptBuilder $builder,
    ) {
        //
    }

    /**
     * Commercially accept a vendor invoice.
     */
    public function accept(
        SiiInboundDocument $document,
        Rut $signer,
        string $location,
        DigitalCertificate $certificate,
        ?DateTimeImmutable $signedAt = null
    ): string {
        $this->transitionClaim(
            $document,
            fn(SiiInboundDocument $d) => $this->gateway->accept($d),
            InboundDteStatus::CommercialAccepted,
            'ACD'
        );

        $receiptXml = $this->builder->build($document, $signer, $location, $certificate, $signedAt);

        $this->event->dispatch(new InboundDteAcknowledged($document, $receiptXml));

        return $receiptXml;
    }

    /**
     * Reject a vendor invoice commercially (Reclamo al Contenido).
     */
    public function reject(SiiInboundDocument $document, string $reason = ''): void
    {
        $this->transitionClaim(
            $document,
            fn(SiiInboundDocument $d) => $this->gateway->reject($d, $reason),
            InboundDteStatus::CommercialRejected,
            'RCD'
        );
    }

    /**
     * Reject a vendor invoice for missing goods (Falta Total).
     */
    public function rejectGoods(SiiInboundDocument $document, string $reason = ''): void
    {
        $this->transitionClaim(
            $document,
            fn(SiiInboundDocument $d) => $this->gateway->rejectGoods($d, $reason),
            InboundDteStatus::CommercialRejected,
            'ERM'
        );
    }

    /**
     * Reject a vendor invoice for partially missing goods (Falta Parcial).
     */
    public function rejectPartial(SiiInboundDocument $document, string $reason = ''): void
    {
        $this->transitionClaim(
            $document,
            fn(SiiInboundDocument $d) => $this->gateway->rejectPartial($d, $reason),
            InboundDteStatus::CommercialRejected,
            'RFP'
        );
    }

    /**
     * Confirm receipt of goods or services (Acuse de Recibo de Mercaderías / RMA).
     */
    public function confirmGoodsReceipt(SiiInboundDocument $document, string $reason = ''): void
    {
        $this->transitionClaim(
            $document,
            fn(SiiInboundDocument $d) => $this->gateway->confirmGoodsReceipt($d, $reason),
            InboundDteStatus::GoodsReceipt,
            'RMA'
        );
    }

    /**
     * Transition a document's claim status after a gateway operation.
     */
    protected function transitionClaim(
        SiiInboundDocument $document,
        callable $gatewayCall,
        InboundDteStatus $newStatus,
        string $claimCode
    ): void {
        // Claims the document with a single conditional UPDATE before touching
        // the gateway: a concurrent claim racing this one loses here and aborts
        // instead of hitting the SII webservice twice. No transaction spans the
        // external gateway call.
        $this->ensureNotClaimed($document);

        $this->claim($document);

        $gatewayCall($document);

        $document->forceFill([
            'status' => $newStatus,
            'claimed_at' => $this->date->now(),
            'claim_status' => $claimCode,
        ])->save();

        $this->event->dispatch(new InboundDteAnswered($document));
    }

    /**
     * Claim an unclaimed document for this operation.
     *
     * @throws LogicException When another operation already claimed the document.
     */
    protected function claim(SiiInboundDocument $document): void
    {
        $claimed = SiiInboundDocument::query()
            ->whereKey($document->getKey())
            ->whereNotIn('status', [
                InboundDteStatus::CommercialAccepted,
                InboundDteStatus::CommercialRejected,
            ])
            ->update(['updated_at' => $this->date->now()]);

        if ($claimed < 1) {
            throw new LogicException(
                "The document [{$document->getKey()}] has already been commercially claimed or accepted."
            );
        }
    }

    /**
     * Ensure the document has not already been commercially accepted or rejected.
     */
    protected function ensureNotClaimed(SiiInboundDocument $document): void
    {
        if (in_array($document->status, [
            InboundDteStatus::CommercialAccepted,
            InboundDteStatus::CommercialRejected,
        ], true)) {
            throw new LogicException('The document has already been commercially claimed or accepted.');
        }
    }
}
