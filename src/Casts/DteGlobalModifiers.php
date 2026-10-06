<?php

namespace Laragear\Dte\Casts;

use Laragear\Dte\Validation\DteRules;
use XMLWriter;

use function filled;
use function round;
use function substr;

/**
 * The Documento > DscRcgGlobal block: global discounts and surcharges.
 *
 * @property list<array<string, mixed>> $items The modifier rows:
 *     type (D|R), value_type (%|$), value, description, target.
 */
class DteGlobalModifiers extends DteBlock
{
    /**
     * Validation rules for the block attributes.
     *
     * @var array<string, string>
     */
    public const array RULES = DteRules::GLOBAL_MODIFIERS;

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
     * Append the global discount and surcharge lines.
     */
    public function toXml(XMLWriter $writer): void
    {
        foreach ($this['items'] as $index => $modifier) {
            $writer->startElement('DscRcgGlobal');

            $writer->writeElement('NroLinDR', (string) ($index + 1));
            $writer->writeElement('TpoMov', (string) $modifier['type']);

            if (filled($modifier['description'] ?? null)) {
                $writer->writeElement('GlosaDR', substr((string) $modifier['description'], 0, 45));
            }

            $writer->writeElement('TpoValor', (string) $modifier['value_type']);
            $writer->writeElement('ValorDR', (string) round((float) $modifier['value'], 4));

            if (($modifier['target'] ?? 0) > 0) {
                $writer->writeElement('IndExeDR', (string) $modifier['target']);
            }

            $writer->endElement(); // DscRcgGlobal
        }
    }
}
