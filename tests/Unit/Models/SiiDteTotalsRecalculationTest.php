<?php

namespace Tests\Unit\Models;

use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use LogicException;
use Tests\DatabaseTestCase;

class SiiDteTotalsRecalculationTest extends DatabaseTestCase
{
    public function test_replaces_detail_items_and_recalculates_totals_in_memory(): void
    {
        $dte = $this->createDraftInvoice();

        $dte->detail_items = ['items' => [
            $this->item('Product B', 2000, 2), // 4000 net
            $this->item('Exempt B', 500, 1, exempt: true),
        ]];

        static::assertSame(4000, $dte->amount_net);
        static::assertSame(500, $dte->amount_exempt);
        static::assertSame(760, $dte->amount_taxes);
        static::assertSame(5260, $dte->amount_total);
    }

    public function test_accepts_a_bare_item_list_when_replacing_detail_items(): void
    {
        $dte = $this->createDraftInvoice();

        $dte->detail_items = [$this->item('Product B', 1000, 1)];

        static::assertSame(1000, $dte->amount_net);
        static::assertSame(1190, $dte->amount_total);
    }

    public function test_recalculates_the_items_block_from_the_model_items(): void
    {
        $dte = $this->createDraftInvoice();

        $dte->detail_items = ['items' => [$this->item('Product B', 1000, 3)]];

        static::assertSame(['Product B'], $dte->items->map->name->all());
        static::assertSame(3000, $dte->amount_net);
    }

    public function test_saving_a_draft_persists_recalculated_amounts_and_header_totals(): void
    {
        $dte = $this->createDraftInvoice();

        $dte->detail_items = ['items' => [$this->item('Product B', 2500, 2)]];

        $dte->save();

        $fresh = $dte->fresh();
        $fresh->load('payload');

        static::assertSame(5000, $fresh->amount_net);
        static::assertSame(950, $fresh->amount_taxes);
        static::assertSame(5950, $fresh->amount_total);
        static::assertSame(5000, $fresh->payload->header_totals['net']);
        static::assertSame(950, $fresh->payload->header_totals['tax']);
        static::assertSame(5950, $fresh->payload->header_totals['total']);
    }

    public function test_recalculated_totals_match_the_builder_math(): void
    {
        $dte = $this->createDraftInvoice();

        // 10000 net, minus 10% global discount = 9000, plus a 1710 retention.
        $dte->payload->global_modifiers = ['items' => [
            ['type' => 'D', 'value_type' => '%', 'value' => 10, 'target' => 0, 'description' => 'Discount'],
        ]];

        $dte->detail_items = ['items' => [
            $this->item('Product B', 1000, 10, taxes: ['15' => 1710]),
        ]];

        // Net 9000, IVA 1710, retention 1710 subtracted: total 9000 + 1710 - 1710 = 9000.
        static::assertSame(9000, $dte->amount_net);
        static::assertSame(1710, $dte->amount_taxes);
        static::assertSame(9000, $dte->amount_total);
    }

    public function test_manual_payload_edits_are_recalculated_with_the_public_method(): void
    {
        $dte = $this->createDraftInvoice();

        $dte->payload->detail_items = ['items' => [$this->item('Product B', 4000, 1)]];

        // Stale amounts until recalculated.
        static::assertNotSame(4000, $dte->amount_net);

        $dte->recalculateTotals();

        static::assertSame(4000, $dte->amount_net);
        static::assertSame(4760, $dte->amount_total);
        static::assertSame(4000, $dte->payload->header_totals['net']);
    }

    public function test_saving_a_non_draft_dte_does_not_recalculate(): void
    {
        $dte = $this->createDraftInvoice();

        $dte->detail_items = ['items' => [$this->item('Product B', 1000, 1)]];
        $dte->save();

        $dte->forceFill(['status' => DteStatus::Accepted])->save();
        $dte->load('payload');

        $dte->detail_items = ['items' => [$this->item('Product B', 9999, 5)]];

        $dte->save();

        $fresh = $dte->fresh();
        $fresh->load('payload');

        // The accepted document keeps the totals the SII already saw.
        static::assertSame(1000, $fresh->amount_net);
        static::assertSame(1000, $fresh->payload->header_totals['net']);
    }

    public function test_aggregated_item_taxes_are_persisted_on_recalculation(): void
    {
        $dte = $this->createDraftInvoice();

        $dte->detail_items = ['items' => [
            $this->item('Product B', 1000, 1, taxes: ['25' => 50]),
        ]];

        $dte->save();

        static::assertSame(['25' => 50], $dte->fresh()->taxes);
    }

    public function test_empty_taxes_persist_as_null(): void
    {
        $dte = $this->createDraftInvoice();

        $dte->detail_items = ['items' => [$this->item('Product B', 1000, 1)]];
        $dte->save();

        static::assertNull($dte->fresh()->taxes);
    }

    public function test_replacing_detail_items_without_payload_throws(): void
    {
        $dte = SiiDte::factory()->invoice()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE has no payload to store detail items.');

        $dte->detail_items = ['items' => [$this->item('Product B', 1000, 1)]];
    }

    public function test_recalculating_without_payload_throws(): void
    {
        $dte = SiiDte::factory()->invoice()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE has no payload to calculate totals from.');

        $dte->recalculateTotals();
    }

    /**
     * Create a draft invoice with a payload carrying one taxable item.
     */
    protected function createDraftInvoice(): SiiDte
    {
        $dte = SiiDte::factory()->invoice()->create();

        $dte->payload()->create([
            'header_id_doc' => [
                'document_type' => DteType::Invoice->value,
                'issued_on' => '2026-08-15',
            ],
            'header_totals' => ['taxes' => [], 'net' => 0, 'exempt' => 0, 'tax' => 0, 'total' => 0],
            'detail_items' => ['items' => [$this->item('Product A', 1000, 1)]],
            'references' => ['items' => []],
            'global_modifiers' => ['items' => []],
        ]);

        return $dte;
    }

    /**
     * Make a detail item row for the payload block.
     *
     * @param  array<string, int>|null  $taxes
     * @return array<string, mixed>
     */
    protected function item(string $name, int $unitPrice, int $quantity, bool $exempt = false, ?array $taxes = null): array
    {
        return [
            'name' => $name,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'description' => null,
            'unit' => null,
            'code' => null,
            'code_type' => null,
            'discount_percentage' => 0,
            'discount_amount' => null,
            'exempt' => $exempt,
            'taxes' => $taxes ?? [],
        ];
    }
}
