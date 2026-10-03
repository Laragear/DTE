<?php

namespace Tests\Unit\Enums;

use Illuminate\Support\Collection;
use Laragear\Dte\Enums\ReferenceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReferenceTypeTest extends TestCase
{
    public static function providesLabels(): array
    {
        return [
            ReferenceType::PurchaseOrder->value => [ReferenceType::PurchaseOrder, 'Orden de Compra'],
            ReferenceType::OrderNote->value => [ReferenceType::OrderNote, 'Nota de Pedido'],
            ReferenceType::Contract->value => [ReferenceType::Contract, 'Contrato'],
            ReferenceType::Resolution->value => [ReferenceType::Resolution, 'Resolución'],
            ReferenceType::ChileCompraProcess->value => [ReferenceType::ChileCompraProcess, 'Proceso ChileCompra'],
            ReferenceType::ChileCompraFile->value => [ReferenceType::ChileCompraFile, 'Ficha ChileCompra'],
            ReferenceType::Dus->value => [ReferenceType::Dus, 'Documento Único de Salida'],
            ReferenceType::BillOfLading->value => [ReferenceType::BillOfLading, 'B/L (Conocimiento de Embarque)'],
            ReferenceType::AirWaybill->value => [ReferenceType::AirWaybill, 'Guía Aérea'],
            ReferenceType::MicDta->value => [ReferenceType::MicDta, 'Manifiesto Internacional de Carga'],
            ReferenceType::Waybill->value => [ReferenceType::Waybill, 'Carta de Porte'],
            ReferenceType::SnaResolution->value => [
                ReferenceType::SnaResolution, 'Resolución del SNA donde califica Servicios de Exportación'
            ],
            ReferenceType::Passport->value => [ReferenceType::Passport, 'Pasaporte'],
            ReferenceType::DepositCertificate->value => [
                ReferenceType::DepositCertificate, 'Certificado de Depósito Bolsa de Productos de Chile'
            ],
            ReferenceType::PledgeVoucher->value => [
                ReferenceType::PledgeVoucher, 'Vale de Prenda Bolsa de Productos de Chile'
            ],
            ReferenceType::TestSet->value => [ReferenceType::TestSet, 'Set de Pruebas'],
            ReferenceType::ServiceEntrySheet->value => [
                ReferenceType::ServiceEntrySheet, 'Hoja de Entrada de Servicios'
            ],
        ];
    }

    #[DataProvider('providesLabels')]
    public function test_provides_label_for_each_case(ReferenceType $type, string $expected): void
    {
        static::assertSame($expected, $type->label());
    }

    public function test_collect_returns_all_cases(): void
    {
        $collection = ReferenceType::collect();

        static::assertInstanceOf(Collection::class, $collection);
        static::assertCount(17, $collection);
        static::assertSame(ReferenceType::cases(), $collection->all());
    }

    public function test_options_returns_value_label_map(): void
    {
        $options = ReferenceType::options();

        static::assertInstanceOf(Collection::class, $options);
        static::assertCount(17, $options);
        static::assertSame('Orden de Compra', $options->get('801'));
        static::assertSame('Hoja de Entrada de Servicios', $options->get('HES'));
        static::assertSame('Set de Pruebas', $options->get('SET'));
    }
}
