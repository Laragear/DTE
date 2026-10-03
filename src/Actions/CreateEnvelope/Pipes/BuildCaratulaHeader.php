<?php

namespace Laragear\Dte\Actions\CreateEnvelope\Pipes;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\CreateEnvelope\Assembly;
use Laragear\Dte\Enums\SiiRut;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Support\Timestamp;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Rut\Rut;
use RuntimeException;
use UnexpectedValueException;
use XMLWriter;
use function is_int;

class BuildCaratulaHeader
{
    /**
     * Create a Build Caratula Header pipe instance.
     */
    public function __construct(
        protected Repository $config,
        protected XmlDomFactory $xml,
        protected DateFactory $date,
    ) {
        //
    }

    /**
     * Handle the incoming DTE Envelope Assembly.
     *
     * @param  Closure(Assembly): Assembly  $next
     */
    public function handle(Assembly $assembly, Closure $next): Assembly
    {
        $writer = $this->writer($assembly->requirePath());

        $this->openEnvelope($writer, $assembly);
        $this->writeCaratula($assembly, $writer);

        $assembly->writer = $writer;

        return $next($assembly);
    }

    /**
     * Create a file-backed XML writer.
     */
    protected function writer(string $path): XMLWriter
    {
        $writer = $this->xml->writer();

        if (!$writer->openUri($path)) {
            throw new RuntimeException('Unable to open the temporary envelope XML file.');
        }

        return $writer;
    }

    /**
     * Write the EnvioDTE and SetDTE opening elements.
     */
    protected function openEnvelope(XMLWriter $writer, Assembly $assembly): void
    {
        $tag = $assembly->envelope->type === 'boleta' ? 'EnvioBOLETA' : 'EnvioDTE';

        $writer->startDocument('1.0', XmlDomFactory::ENCODING);
        $writer->startElementNs(null, $tag, XmlDomFactory::XML_NAMESPACE);

        $writer->writeAttributeNs(
            'xsi',
            'schemaLocation',
            'http://www.w3.org/2001/XMLSchema-instance',
            XmlDomFactory::XML_NAMESPACE.' EnvioDTE_v10.xsd'
        );
        $writer->writeAttribute('version', '1.0');

        $writer->text("\n");

        $writer->startElement('SetDTE');
        $writer->writeAttribute('ID', 'SetDoc');
    }

    /**
     * Write the required Caratula values.
     */
    protected function writeCaratula(Assembly $assembly, XMLWriter $writer): void
    {
        $envelope = $assembly->envelope;

        $writer->text("\n");
        $writer->startElement('Caratula');
        $writer->writeAttribute('version', '1.0');
        $this->caratulaElement($writer, 'RutEmisor', $envelope->issuer_rut->formatBasic());
        $this->caratulaElement($writer, 'RutEnvia', $envelope->sender_rut->formatBasic());
        $this->caratulaElement($writer, 'RutReceptor',
            $assembly->targetReceiverRut?->formatBasic() ?? SiiRut::Sii->formatBasic());
        $this->caratulaElement($writer, 'FchResol', $this->resolutionDate($assembly));
        $this->caratulaElement($writer, 'NroResol', (string) $this->resolutionNumber($assembly));
        $this->caratulaElement($writer, 'TmstFirmaEnv', Timestamp::formatSii($this->date->now('America/Santiago')));

        $this->writeSubtotal($assembly, $writer);

        $writer->endElement(); // Caratula
        $writer->text("\n");
        $writer->flush();
    }

    /**
     * Write a single Caratula element with its value.
     */
    protected function caratulaElement(XMLWriter $writer, string $name, string $value): void
    {
        $writer->startElement($name);
        $writer->text($value);
        $writer->endElement();
        $writer->text("\n");
    }

    /**
     * Write a SubTotDTE for each document type present in the envelope.
     */
    protected function writeSubtotal(Assembly $assembly, XMLWriter $writer): void
    {
        /** @var EloquentCollection<int, SiiDteEnvelope> $counts */
        $counts = $assembly->envelope
            ->dtes()
            ->when($assembly->targetReceiverRut, static function (EloquentBuilder $query, Rut $rut): void {
                $query->where('receiver_num', $rut->num);
            })
            ->select('document_type')
            ->selectRaw('count(*) as total')
            ->groupBy('document_type')
            ->orderBy('document_type')
            ->get();

        foreach ($counts as $row) {
            $writer->text("\n");
            $writer->startElement('SubTotDTE');
            $writer->startElement('TpoDTE');
            $writer->text((string) $row->document_type->value);
            $writer->endElement();
            $writer->startElement('NroDTE');
            $writer->text((string) $row->total);
            $writer->endElement();
            $writer->endElement();
        }

        $writer->text("\n");
    }

    /**
     * Return the configured strict issuer resolution date.
     */
    protected function resolutionDate(Assembly $assembly): string
    {
        $date = $assembly->envelope->resolution_date;

        if (!$date) {
            throw new UnexpectedValueException('The issuer resolution date must use YYYY-MM-DD format.');
        }

        return $date->format('Y-m-d');
    }

    /**
     * Return the configured issuer resolution number.
     */
    protected function resolutionNumber(Assembly $assembly): int
    {
        $value = $assembly->envelope->resolution_number;

        if (is_int($value) && $value >= 0) {
            return $value;
        }

        throw new UnexpectedValueException('The issuer resolution number must be a non-negative integer.');
    }
}
