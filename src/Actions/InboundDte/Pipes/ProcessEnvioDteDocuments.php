<?php

namespace Laragear\Dte\Actions\InboundDte\Pipes;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\InboundDte\InboundDteData;
use Laragear\Dte\Contracts\TenantResolver;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Events\InboundDteReceived;
use Laragear\Dte\Events\InboundForgedDteReceived;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Dte\Models\SiiInterchangeLog;
use Laragear\Dte\Services\DteAuthenticityVerifier;
use Laragear\Rut\Rut;
use RuntimeException;
use SimpleXMLElement;

class ProcessEnvioDteDocuments
{
    /**
     * Create a new Process Envio Dte Documents instance.
     */
    public function __construct(
        protected TenantResolver $tenantResolver,
        protected DteAuthenticityVerifier $authenticityVerifier,
        protected Dispatcher $event,
        protected DateFactory $date,
    ) {
        //
    }

    /**
     * Handle the incoming Inbound DTE.
     *
     * @param  Closure(InboundDteData):InboundDteData  $next
     */
    public function handle(InboundDteData $data, Closure $next): InboundDteData
    {
        if ($data->rootName !== 'EnvioDTE') {
            return $next($data);
        }

        $receiver = $this->resolveReceiver($data->xml);
        $tenant = $this->resolveTenant($receiver);
        $data->log->update(['recipient' => $receiver->formatBasic()]);

        foreach ($data->xml->SetDTE->DTE as $dteNode) {
            $this->processDocument($dteNode, $receiver, $data->log, $tenant);
        }

        return $next($data);
    }

    /**
     * Extracts and parses the receiver's RUT identifier from the XML envelope.
     */
    protected function resolveReceiver(SimpleXMLElement $xml): Rut
    {
        if (! isset($xml->SetDTE->Caratula->RutReceptor)) {
            throw new RuntimeException('Missing RutReceptor in EnvioDTE.');
        }

        return Rut::parse((string) $xml->SetDTE->Caratula->RutReceptor);
    }

    /**
     * Finds and returns the tenant model associated with the given receiver RUT.
     */
    protected function resolveTenant(Rut $receiver): object
    {
        $tenant = $this->tenantResolver->resolve($receiver);

        if ($tenant === null) {
            throw new RuntimeException("Tenant for RUT {$receiver->formatBasic()} not found.");
        }

        return $tenant;
    }

    /**
     * Persist, verify, and dispatch events for a document.
     */
    protected function processDocument(
        SimpleXMLElement $dteNode,
        Rut $receiver,
        SiiInterchangeLog $log,
        object $tenant,
    ): void {
        $document = $this->persistDocument($dteNode, $receiver, $log);

        $isAuthentic = $this->authenticityVerifier->verify(
            $document->issuer_rut,
            $receiver,
            $document->document_type,
            $document->folio,
            $document->issued_on,
            $document->amount_total,
        );

        $document->update([
            'status' => $isAuthentic
                ? InboundDteStatus::Received
                : InboundDteStatus::Forged,
        ]);

        $this->dispatchResult($document, $tenant, $isAuthentic);
    }

    /**
     * Saves document metadata and raw XML payload into the database.
     */
    protected function persistDocument(
        SimpleXMLElement $dteNode,
        Rut $receiver,
        SiiInterchangeLog $log,
    ): SiiInboundDocument {
        $metadata = $this->extractMetadata($dteNode);

        // A phantom spawned by the RCV sync already holds the unique key: it is
        // adopted instead of colliding with the unique index. The XML always
        // wins on header values, but a phantom's status never regresses.
        $document = SiiInboundDocument::query()
            ->where('issuer_num', $metadata['issuer']->num)
            ->where('issuer_vd', $metadata['issuer']->vd)
            ->where('receiver_num', $receiver->num)
            ->where('receiver_vd', $receiver->vd)
            ->where('document_type', $metadata['type']->value)
            ->where('folio', $metadata['folio'])
            ->firstOrNew();

        $document->forceFill([
            'issuer_rut' => $metadata['issuer'],
            'receiver_rut' => $receiver,
            'document_type' => $metadata['type'],
            'folio' => $metadata['folio'],
            'sii_interchange_log_id' => $log->id,
            'issued_on' => $metadata['issued_on'],
            'amount_total' => $metadata['amount'],
            'status' => $document->exists ? $document->status : InboundDteStatus::DEFAULT,
            'received_at' => $this->date->now(),
            'validated_at' => $this->date->now(),
        ])->save();

        $document->payload()->updateOrCreate([], ['xml' => $metadata['xml']]);

        return $document;
    }

    /**
     * Parses XML nodes to retrieve issuer, type, folio, dates, and amounts.
     *
     * @return array{issuer: Rut, type: DteType, folio: int, issued_on: Carbon, amount: int, xml: string}
     */
    protected function extractMetadata(SimpleXMLElement $dteNode): array
    {
        $encabezado = $dteNode->Documento->Encabezado;

        return [
            'issuer' => Rut::parse((string) $encabezado->Emisor->RUTEmisor),
            'type' => DteType::from((int) $encabezado->IdDoc->TipoDTE),
            'folio' => (int) $encabezado->IdDoc->Folio,
            'issued_on' => Carbon::parse((string) $encabezado->IdDoc->FchEmis),
            'amount' => (int) $encabezado->Totales->MntTotal,
            'xml' => $dteNode->asXML(),
        ];
    }

    /**
     * Dispatch events for the verification result.
     */
    protected function dispatchResult(SiiInboundDocument $document, object $tenant, bool $isAuthentic): void
    {
        $this->event->dispatch(
            $isAuthentic
                ? new InboundDteReceived($document, $tenant)
                : new InboundForgedDteReceived($document, $tenant)
        );
    }
}
