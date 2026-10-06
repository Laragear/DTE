<?php

namespace Laragear\Dte\Validation;

use Laragear\Dte\Models\SiiDtePayload;

use function array_merge;

/**
 * Validate a DTE payload against the XSD-derived block rules.
 */
final class DocumentValidator
{
    /**
     * Blocks a document payload cannot omit.
     *
     * @var list<string>
     */
    protected const array REQUIRED_BLOCKS = [
        'header_id_doc',
        'header_issuer',
        'header_receiver',
        'header_totals',
        'detail_items',
    ];

    /**
     * Validate the document payload blocks.
     *
     * @param  array<string, array<string, mixed>|null>  $blocks
     */
    public function validate(array $blocks): void
    {
        validator($blocks, $this->rules($blocks))->validate();
    }

    /**
     * Return the combined rules for the given payload blocks.
     *
     * Blocks that carry no data are skipped, so their rules only
     * apply when the block is present in the payload.
     *
     * @param  array<string, array<string, mixed>|null>  $blocks
     * @return array<string, string>
     */
    public function rules(array $blocks): array
    {
        $rules = [];

        foreach (static::REQUIRED_BLOCKS as $column) {
            $rules[$column] = 'required|array';
        }

        foreach (SiiDtePayload::BLOCKS as $column => $block) {
            if (isset($blocks[$column])) {
                $rules = array_merge($rules, $this->prefix($column.'.', $block::RULES));
            }
        }

        return $rules;
    }

    /**
     * Prefix every block rule with its payload path.
     *
     * @param  array<string, string>  $rules
     * @return array<string, string>
     */
    private function prefix(string $path, array $rules): array
    {
        $prefixed = [];

        foreach ($rules as $key => $rule) {
            $prefixed[$path.$key] = $rule;
        }

        return $prefixed;
    }
}
