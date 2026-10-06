<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use XMLWriter;

use function now;

/**
 * The Documento > Referencia block: referenced documents.
 *
 * @property list<array<string, mixed>> $items The reference rows:
 *     document_type, folio, date, reference_code, reason.
 */
class DteReferences extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::REFERENCES;

    /**
     * The default attributes applied when a key is missing.
     *
     * @return array<string, mixed>
     */
    protected static function defaults(): array
    {
        return ['items' => []];
    }

    /**
     * Append all document references.
     */
    public function toXml(XMLWriter $writer): void
    {
        foreach ($this['items'] as $index => $reference) {
            $writer->startElement('Referencia');

            $writer->writeElement('NroLinRef', (string) ($index + 1));
            $writer->writeElement('TpoDocRef', (string) $reference['document_type']);
            $this->optionalElement($writer, 'FolioRef', (string) ($reference['folio'] ?? null));
            $writer->writeElement('FchRef',
                (string) ($reference['date'] ?? now('America/Santiago')->toDateString()));
            $this->optionalElement($writer, 'CodRef', (string) ($reference['reference_code'] ?? null));
            $this->optionalElement($writer, 'RazonRef', (string) ($reference['reason'] ?? null));

            $writer->endElement(); // Referencia
        }
    }
}
