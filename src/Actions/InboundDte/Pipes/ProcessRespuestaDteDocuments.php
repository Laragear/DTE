<?php

namespace Laragear\Dte\Actions\InboundDte\Pipes;

use Closure;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\InboundDte\InboundDteData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Rut\Rut;
use SimpleXMLElement;

class ProcessRespuestaDteDocuments
{
    /**
     * Create a new Process Respuesta Dte Documents instance.
     */
    public function __construct(
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
        if ($data->rootName !== 'RespuestaDTE') {
            return $next($data);
        }

        $issuerRut = $this->resolveRespondee($data->xml);

        foreach ($data->xml->Resultado->ResultadoDTE as $resultadoDte) {
            $this->updateDocumentStatus($issuerRut, $resultadoDte);
        }

        return $next($data);
    }

    /**
     * Validates the XML response author's RUT and returns the recipient's parsed RUT.
     */
    protected function resolveRespondee(SimpleXMLElement $xml): Rut
    {
        Rut::parse((string) $xml->Resultado->Caratula->RutResponde)->validate();

        return Rut::parse((string) $xml->Resultado->Caratula->RutRecibe);
    }

    /**
     * Apply the new status transition to the matching DTE.
     */
    protected function updateDocumentStatus(Rut $issuerRut, SimpleXMLElement $resultadoDte): void
    {
        $siiDte = $this->findMatchingDte($issuerRut, $resultadoDte);

        if ($siiDte === null) {
            return;
        }

        $this->applyStatusTransition($siiDte, (string) $resultadoDte->EstadoDTE);
    }

    /**
     * Find the SiiDte by issuer RUT, document type, and folio.
     */
    protected function findMatchingDte(Rut $issuerRut, SimpleXMLElement $resultadoDte): ?SiiDte
    {
        return SiiDte::query()
            ->where('issuer_num', $issuerRut->num)
            ->where('document_type', DteType::from((int) $resultadoDte->TipoDTE))
            ->where('folio', (int) $resultadoDte->Folio)
            ->first();
    }

    /**
     * Stamp the acceptance or rejection timestamps by status code.
     */
    protected function applyStatusTransition(SiiDte $siiDte, string $estadoDte): void
    {
        if ($estadoDte === '0' || $estadoDte === 'ACD') {
            $siiDte->accepted_at ??= $this->date->now();
        } elseif ($estadoDte === '1' || $estadoDte === 'RCD') {
            $siiDte->rejected_at ??= $this->date->now();
        }

        $siiDte->touch('acknowledged_at');
    }
}
