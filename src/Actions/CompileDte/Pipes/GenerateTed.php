<?php

namespace Laragear\Dte\Actions\CompileDte\Pipes;

use const LIBXML_NONET;

use Closure;
use DOMDocument;
use DOMElement;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Caf\CafParser;
use Laragear\Dte\Support\Timestamp;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\TimbreSigner;
use RuntimeException;
use Throwable;

use function mb_substr;

class GenerateTed
{
    /**
     * Create a Generate TED pipe instance.
     */
    public function __construct(
        protected CafParser $caf,
        protected TimbreSigner $signer,
        protected XmlDomFactory $xml,
        protected DateFactory $date,
    ) {
        //
    }

    /**
     * Handle the incoming DTE compilation.
     *
     * @param  Closure(Compilation): Compilation  $next
     */
    public function handle(Compilation $compilation, Closure $next): Compilation
    {
        $document = $compilation->requireDocument();

        $caf = $compilation->dte->caf ?? throw new RuntimeException('The DTE does not have an allocated CAF.');

        $compilation->cafData ??= $this->caf->parse($caf->xml);

        $ted = $document->createElement('TED');
        $ted->setAttribute('version', '1.0');

        $details = $document->createElement('DD');

        $ted->appendChild($details);

        $this->appendDetails($compilation, $details);
        $this->appendSignature($compilation, $ted, $details);

        $compilation->cafData = null;
        $compilation->ted = $ted;

        return $next($compilation);
    }

    /**
     * Append the DD fields in the strict SII sequence.
     */
    protected function appendDetails(Compilation $compilation, DOMElement $details): void
    {
        $dte = $compilation->dte;
        $payload = $compilation->payload();

        $this->element($details, 'RE', $dte->issuer_rut->formatBasic());
        $this->element($details, 'TD', $dte->document_type->value);
        $this->element($details, 'F', $dte->folio);
        $this->element($details, 'FE', $payload->header_id_doc->issued_on);
        $this->element($details, 'RR', $dte->receiver_rut->formatBasic());
        $this->element($details, 'RSR', mb_substr($payload->header_receiver->name, 0, 40));
        $this->element($details, 'MNT', $dte->amount_total);

        $itemName = (string) $payload->detail_items->string('items.0.name');

        if ($itemName !== '') {
            $this->element($details, 'IT1', mb_substr($itemName, 0, 40));
        }

        $details->appendChild($details->ownerDocument->importNode($this->cafNode($compilation), true));

        $this->element($details, 'TSTED', Timestamp::formatSii($this->date->now('America/Santiago')));
    }

    /**
     * Append the CAF-backed signature over DD.
     */
    protected function appendSignature(Compilation $compilation, DOMElement $ted, DOMElement $details): void
    {
        $privateKey = $compilation->requireCafData()['private_key'];

        $signature = $this->element($ted, 'FRMT', $this->signer->sign($details, $privateKey));
        $signature->setAttribute('algoritmo', 'SHA1withRSA');
    }

    /**
     * Extract the CAF element from its persisted authorization.
     */
    protected function cafNode(Compilation $compilation): DOMElement
    {
        $document = $this->cafDocument($compilation->requireCafData()['xml']);
        $node = $document->getElementsByTagName('CAF')->item(0);

        return $node instanceof DOMElement
            ? $node
            : throw new RuntimeException('The allocated CAF XML does not contain a CAF element.');
    }

    /**
     * Parse the persisted CAF XML.
     */
    protected function cafDocument(string $xml): DOMDocument
    {
        $document = $this->xml->document(encoding: XmlDomFactory::ENCODING);

        try {
            $document->loadXML($xml, LIBXML_NONET);
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to parse the allocated CAF XML.', previous: $e);
        }

        return $document;
    }

    /**
     * Append a namespaced TED child.
     */
    protected function element(DOMElement $parent, string $name, string|int|null $value): DOMElement
    {
        $element = $parent->ownerDocument->createElement($name, (string) $value);
        $parent->appendChild($element);

        return $element;
    }
}
