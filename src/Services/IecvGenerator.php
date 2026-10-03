<?php

namespace Laragear\Dte\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use Laragear\Dte\Builders\Iecv\IecvBuilder;
use Laragear\Dte\Builders\Iecv\IecvPropertyData;
use Laragear\Dte\Builders\Iecv\IecvPurchaseData;
use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Dte\Xml\XsdValidator;
use Laragear\Rut\Rut;
use RuntimeException;

class IecvGenerator
{
    /**
     * Create a new Iecv Generator instance.
     */
    public function __construct(
        protected CertificateResolver $certificate,
        protected IecvBuilder $builder,
        protected XmlDomFactory $xml,
        protected XsdValidator $xsd,
        protected XmlSigner $signer,
    ) {
        //
    }

    /**
     * Build and sign an IECV Sales Book XML.
     *
     * @param  Collection<int, SiiDte>  $dtes
     * @param  array<int, IecvPropertyData>  $properties
     */
    public function generateSales(
        Rut $issuer,
        Collection $dtes,
        string $period,
        string $resolutionDate,
        int $resolutionNumber,
        Rut $senderRut,
        array $properties = [],
    ): string {
        $unsignedXml = $this->builder->build(
            $dtes,
            IecvType::Sales,
            $period,
            $resolutionDate,
            $resolutionNumber,
            $senderRut,
            $properties,
        );

        return $this->signXml($unsignedXml, $issuer);
    }

    /**
     * Build and sign an IECV Purchases Book XML.
     *
     * @param  array<int, IecvPurchaseData>  $entries
     * @param  array<int, IecvPropertyData>  $properties
     */
    public function generatePurchases(
        Rut $issuer,
        array $entries,
        string $period,
        string $resolutionDate,
        int $resolutionNumber,
        Rut $senderRut,
        array $properties = [],
    ): string {
        $unsignedXml = $this->builder->buildPurchases(
            $entries,
            $period,
            $resolutionDate,
            $resolutionNumber,
            $issuer,
            $senderRut,
            $properties,
        );

        return $this->signXml($unsignedXml, $issuer);
    }

    /**
     * Validate, parse, and sign an unsigned IECV XML string.
     */
    protected function signXml(string $unsignedXml, Rut $issuer): string
    {
        $certificate = $this->certificate->resolve($issuer) ?? throw new RuntimeException(
            "No certificate was found for [$issuer]."
        );

        $this->xsd->validate($unsignedXml, 'LibroCV_v10.xsd');

        $document = $this->parseDocument($unsignedXml);
        $element = $this->findEnvioLibro($document);

        $this->signer->sign($element, $certificate);

        return $document->saveXml();
    }

    /**
     * Parse an XML string into a DOMDocument.
     */
    protected function parseDocument(string $xml): DOMDocument
    {
        $document = $this->xml->document(encoding: XmlDomFactory::ENCODING);

        if (!@$document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Unable to parse the IECV XML.');
        }

        return $document;
    }

    /**
     * Find the EnvioLibro DOMElement inside a parsed document.
     */
    protected function findEnvioLibro(DOMDocument $document): DOMElement
    {
        $nodes = $this->xml->xpath($document)->query('//EnvioLibro');
        $element = $nodes !== false ? $nodes->item(0) : null;

        if (!$element instanceof DOMElement) {
            throw new RuntimeException('Unable to find EnvioLibro in IECV XML.');
        }

        return $element;
    }
}
