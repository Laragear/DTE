<?php

namespace Tests\Unit;

use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Enums\ModifierTarget;
use Override;
use Tests\DatabaseTestCase;
use Tests\Unit\Builders\Fixtures\BuilderFixture;

class ModifiersMathTest extends DatabaseTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConfigurationManager::setCompany(fn() => CompanyData::make(
            IssuerData::make(
                '76.123.456-0',
                'Test Company',
                'Software',
                ['620100'],
                'Test Address 123',
                'Santiago',
                '2025-01-01',
                76000,
                'Santiago',
                '+56212345678',
                'test@example.com',
                'Casa Matriz',
            ),
            '76.123.456-0',
        ));
    }

    public function test_calculates_retentions_and_global_modifiers_properly(): void
    {
        $builder = $this->app
            ->make(InvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver());

        // Item: 10 units at $1000 each = $10,000 Net
        $builder->addItem(new Item(
            name: 'Test',
            unitPrice: 1000.0,
            quantity: 10.0,
        ));

        // Let's add a Global Discount of 10% on Net.
        // new Net = 10000 - 1000 = 9000.
        $builder->globalDiscount(10, true, ModifierTarget::Default, 'Descuento 10%');

        // Item 2: Add Retention (code 15 -> IVA Retenido Total, e.g. 1710 retention)
        // Wait, $builder->items are private, we add another item.
        $builder->addItem(new Item(
            name: 'Item Retenido',
            unitPrice: 100.0,
            quantity: 0.0, // just for the tax
            taxes: [15 => 1710], // Retention code 15
        ));

        // So math is:
        // 1st Item Net: 10,000.
        // 2nd Item Net: 0.
        // Total Base Net: 10,000.
        // Global Discount: 10% on 10,000 = -1,000.
        // Final Net: 9,000.
        // Tax (IVA 19% on Net): 9000 * 0.19 = 1,710.
        // Retention: 1,710 (from Item 2). Effect = -1,710.
        // Final Total: Net (9,000) + IVA (1,710) - Ret (1,710) = 9,000.

        $dte = $builder->build();

        static::assertEquals(9000, $dte->amount_net);
        static::assertEquals(0, $dte->amount_exempt);
        static::assertEquals(1710, $dte->amount_taxes);
        static::assertEquals(9000, $dte->amount_total);
        static::assertEquals([15 => 1710], $dte->taxes);

        // Validate payload modifiers
        $payload = $dte->payload->data;
        static::assertCount(1, $payload['global_modifiers']);
        static::assertEquals('D', $payload['global_modifiers'][0]['type']);
        static::assertEquals(10, $payload['global_modifiers'][0]['value']);

        // Verify Totals inside payload match DB mapping
        static::assertEquals(9000, $payload['totals']['net']);
        static::assertEquals(1710, $payload['totals']['tax']);
        static::assertEquals(9000, $payload['totals']['total']);
    }
}
