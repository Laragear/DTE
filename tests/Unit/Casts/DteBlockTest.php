<?php

namespace Tests\Unit\Casts;

use Illuminate\Support\Fluent;
use Illuminate\Validation\ValidationException;
use Laragear\Dte\Casts\DteDetailItems;
use Laragear\Dte\Casts\DteHeaderIdDoc;
use Laragear\Dte\Casts\DteHeaderTotals;
use Tests\TestCase;
use XMLWriter;

class DteBlockTest extends TestCase
{
    public function test_fluent_setters_map_camel_case_to_snake_case(): void
    {
        $block = DteHeaderIdDoc::make();

        $block->issuedOn('2026-08-15')->documentType(33)->indMntNeto(1);

        static::assertSame('2026-08-15', $block->issued_on);
        static::assertSame(33, $block->document_type);
        static::assertSame(1, $block->ind_mnt_neto);
    }

    public function test_fluent_getters_read_snake_case_keys_from_camel_case(): void
    {
        $block = DteHeaderIdDoc::make(['issued_on' => '2026-08-15']);

        static::assertSame('2026-08-15', $block->issuedOn);
        static::assertSame('2026-08-15', $block->issued_on);
    }

    public function test_fluent_setter_without_arguments_stores_true(): void
    {
        $block = DteHeaderIdDoc::make();

        $block->taxExempt();

        static::assertTrue($block->tax_exempt);
    }

    public function test_dynamic_calls_delegate_to_registered_macros(): void
    {
        try {
            Fluent::macro('fancyValue', fn (string $value) => $value.'!');

            $block = DteHeaderIdDoc::make();

            static::assertSame('x!', $block->fancyValue('x'));
            static::assertArrayNotHasKey('fancy_value', $block->toArray());
        } finally {
            Fluent::flushMacros();
        }
    }

    public function test_make_applies_defaults_for_missing_keys(): void
    {
        static::assertSame(['items' => []], DteDetailItems::make()->toArray());
        static::assertSame(
            ['items' => [['name' => 'A']], 'other' => 'keep'],
            DteDetailItems::make(['items' => [['name' => 'A']], 'other' => 'keep'])->toArray(),
        );
    }

    public function test_validate_passes_with_valid_attributes(): void
    {
        DteHeaderTotals::make(['net' => 1000, 'tax' => 190, 'total' => 1190])->validate();

        static::assertTrue(true);
    }

    public function test_validate_throws_with_invalid_attributes(): void
    {
        $this->expectException(ValidationException::class);

        DteHeaderTotals::make(['net' => -1, 'total' => 1190])->validate();
    }

    public function test_blocks_are_json_serializable(): void
    {
        $block = DteHeaderTotals::make(['total' => 1190]);

        static::assertSame('{"total":1190}', $block->toJson());
        static::assertSame(['total' => 1190], $block->jsonSerialize());
    }

    public function test_blocks_support_array_access(): void
    {
        $block = DteHeaderTotals::make(['total' => 1190]);

        static::assertSame(1190, $block['total']);

        $block['net'] = 1000;

        static::assertSame(1000, $block->net);
    }

    public function test_to_xml_appends_the_block_elements(): void
    {
        $writer = new XMLWriter;
        $writer->openMemory();

        DteHeaderTotals::make(['net' => 1000, 'exempt' => 0, 'tax' => 190, 'total' => 1190])->toXml($writer);

        $xml = $writer->outputMemory();

        static::assertStringContainsString('<MntNeto>1000</MntNeto>', $xml);
        static::assertStringContainsString('<IVA>190</IVA>', $xml);
        static::assertStringContainsString('<MntTotal>1190</MntTotal>', $xml);
        static::assertStringNotContainsString('<MntExe>', $xml);
    }
}
