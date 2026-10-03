<?php

namespace Laragear\Dte\Enums;

enum ReferenceType: string
{
    use Concerns\EnumHelpers;

    /** Purchase Order (Orden de Compra) */
    case PurchaseOrder = '801';

    /** Order Note (Nota de pedido) */
    case OrderNote = '802';

    /** Contract (Contrato) */
    case Contract = '803';

    /** Resolution (Resolución) */
    case Resolution = '804';

    /** ChileCompra Process (Proceso ChileCompra) */
    case ChileCompraProcess = '805';

    /** ChileCompra File (Ficha ChileCompra) */
    case ChileCompraFile = '806';

    /** DUS */
    case Dus = '807';

    /** Bill of Lading (B/L) */
    case BillOfLading = '808';

    /** Air Waybill (AWB) */
    case AirWaybill = '809';

    /** MIC/DTA */
    case MicDta = '810';

    /** Waybill (Carta de Porte) */
    case Waybill = '811';

    /** SNA Resolution (Resolución del SNA donde califica Servicios de Exportación) */
    case SnaResolution = '812';

    /** Passport (Pasaporte) */
    case Passport = '813';

    /** Deposit Certificate (Certificado de Depósito Bolsa Prod. Chile) */
    case DepositCertificate = '814';

    /** Pledge Voucher (Vale de Prenda Bolsa Prod. Chile) */
    case PledgeVoucher = '815';

    /** Test Set (Set de pruebas) */
    case TestSet = 'SET';

    /** Service Entry Sheet (Hoja de entrada de servicios) */
    case ServiceEntrySheet = 'HES';

    /**
     * Returns the label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PurchaseOrder => 'Orden de Compra',
            self::OrderNote => 'Nota de Pedido',
            self::Contract => 'Contrato',
            self::Resolution => 'Resolución',
            self::ChileCompraProcess => 'Proceso ChileCompra',
            self::ChileCompraFile => 'Ficha ChileCompra',
            self::Dus => 'Documento Único de Salida',
            self::BillOfLading => 'B/L (Conocimiento de Embarque)',
            self::AirWaybill => 'Guía Aérea',
            self::MicDta => 'Manifiesto Internacional de Carga',
            self::Waybill => 'Carta de Porte',
            self::SnaResolution => 'Resolución del SNA donde califica Servicios de Exportación',
            self::Passport => 'Pasaporte',
            self::DepositCertificate => 'Certificado de Depósito Bolsa de Productos de Chile',
            self::PledgeVoucher => 'Vale de Prenda Bolsa de Productos de Chile',
            self::TestSet => 'Set de Pruebas',
            self::ServiceEntrySheet => 'Hoja de Entrada de Servicios',
        };
    }
}
