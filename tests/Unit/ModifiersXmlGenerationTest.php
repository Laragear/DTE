<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Event;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Actions\CompileDte\Pipes\BuildXml;
use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Enums\ModifierTarget;
use Override;
use Tests\DatabaseTestCase;
use Tests\Unit\Builders\Fixtures\BuilderFixture;

class ModifiersXmlGenerationTest extends DatabaseTestCase
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

    public function test_xml_generation_conforms_to_schema_logic()
    {
        $builder = $this->app
            ->make(InvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver());

        $builder->addItem(new Item(
            name: 'Test',
            unitPrice: 1000.0,
            quantity: 10.0,
            taxes: [15 => 1710],
        ));

        $builder->globalDiscount(10, true, ModifierTarget::Default, 'Descuento Global');

        // Don't fire events that confuse pipeline
        Event::fake();
        $dte = $builder->build();

        // Create manual compilation pass
        $compilation = new Compilation($dte);

        // Pass it through BuildXml
        $pipe = $this->app->make(BuildXml::class);
        $result = $pipe->handle($compilation, fn($c) => $c);

        $xml = $result->document->saveXML();

        static::assertStringContainsString('<DscRcgGlobal>', $xml);
        static::assertStringContainsString('<TpoMov>D</TpoMov>', $xml);
        static::assertStringContainsString('<TpoValor>%</TpoValor>', $xml);
        static::assertStringContainsString('<ValorDR>10</ValorDR>', $xml);
        static::assertStringNotContainsString('<IndExeDR>', $xml);

        static::assertStringContainsString('<CodImpAdic>15</CodImpAdic>', $xml);

        static::assertStringContainsString('<ImptoReten>', $xml);
        static::assertStringContainsString('<TipoImp>15</TipoImp>', $xml);
        static::assertStringContainsString('<MontoImp>1710</MontoImp>', $xml);
    }
}
