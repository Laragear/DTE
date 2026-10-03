<?php

namespace Laragear\Dte\Enums;

use InvalidArgumentException;
use function in_array;

/**
 * This enum is just a helper to subtract from the payable document total when the Tax Code is "retained".
 */
enum SiiTaxes: int
{
    /**
     * Retenible taxes.
     *
     * @const self[]
     */
    protected const array RETENIBLE = [
        self::VatCommercializationMargin,
        self::VatTotalRetained,
        self::VatPartialRetained,
        self::VatAnticipatedMeatSlaughter,
        self::VatAnticipatedMeat,
        self::VatAnticipatedFlour,
        self::VatRetainedLegumes,
        self::VatRetainedWildProducts,
        self::VatRetainedLivestock,
        self::VatRetainedWood,
        self::VatRetainedWheat,
        self::VatRetainedRice,
        self::VatRetainedHydrobiologicals,
        self::VatRetainedScrapMetal,
        self::VatRetainedPpa,
        self::VatRetainedOptional,
        self::VatRetainedConstruction,
        self::Retained42,
        self::Retained43,
    ];

    /** IVA Margen Comercializacion (Factura Venta del Contribuyente) */
    case VatCommercializationMargin = 14;

    /** IVA Retenido Total (Factura Compra del Contribuyente) */
    case VatTotalRetained = 15;

    /** IVA Retenido Parcial (Factura Compra del Contribuyente) */
    case VatPartialRetained = 16;

    /** IVA Anticipado Faenamiento Carne */
    case VatAnticipatedMeatSlaughter = 17;

    /** IVA Anticipado Carne */
    case VatAnticipatedMeat = 18;

    /** IVA Anticipado Harina */
    case VatAnticipatedFlour = 19;

    /** Impuesto Adicional Productos Oro, Joyas, Pieles */
    case AdditionalTaxGoldJewelryFurs = 23;

    /** Impuesto Art. 42 a) Licores, Pisco, Destilados */
    case TaxLiquorsPiscoDistillates = 24;

    /** Impuesto Art. 42 c) Vinos */
    case TaxWines = 25;

    /** Impuesto Art. 42 c) Cervezas y Bebidas Alcoholicas */
    case TaxBeersAlcoholicBeverages = 26;

    /** Impuesto Art. 42 d) y e) Bebidas Analcoholicas y Minerales */
    case TaxNonAlcoholicMineralBeverages = 27;

    /** Impuesto Especifico Diesel */
    case SpecificTaxDiesel = 28;

    /** IVA Retenido Legumbres */
    case VatRetainedLegumes = 30;

    /** IVA Retenido Silvestres */
    case VatRetainedWildProducts = 31;

    /** IVA Retenido Ganado */
    case VatRetainedLivestock = 32;

    /** IVA Retenido Madera */
    case VatRetainedWood = 33;

    /** IVA Retenido Trigo */
    case VatRetainedWheat = 34;

    /** Impuesto Especifico Gasolina */
    case SpecificTaxGasoline = 35;

    /** IVA Retenido Arroz */
    case VatRetainedRice = 36;

    /** IVA Retenido Hidrobiologicas */
    case VatRetainedHydrobiologicals = 37;

    /** IVA Retenido Chatarra */
    case VatRetainedScrapMetal = 38;

    /** IVA Retenido PPA */
    case VatRetainedPpa = 39;

    /** IVA Retenido Opcional */
    case VatRetainedOptional = 40;

    /** IVA Retenido Construccion */
    case VatRetainedConstruction = 41;

    /** Retención 42 */
    case Retained42 = 42;

    /** Retención 43 */
    case Retained43 = 43;

    /** Impuesto Adicional Productos (Alfombras, C. Rodantes, Caviar, Armas) */
    case AdditionalTaxRugsCaviarWeapons = 44;

    /** Impuesto Adicional Productos (Pirotecnia) */
    case AdditionalTaxPyrotechnics = 45;

    /** Bebidas analcoholicas y Minerales con elevado contenido de azucares */
    case TaxNonAlcoholicBeveragesHighSugar = 271;

    /**
     * Check if this Tax Code corresponds to a Retention.
     */
    public function isRetained(): bool
    {
        return in_array($this, self::RETENIBLE, true);
    }

    /**
     * Determines if a given Tax Code corresponds to a Retention.
     */
    public static function isRetention(self|int|string $code): bool
    {
        if (!$code instanceof self) {
            $code = self::tryFrom((int) $code);
        }

        if (!$code) {
            return false;
        }

        return $code->isRetained();
    }

    /**
     * The configured national IVA rate as an integer percentage.
     */
    public static function ivaRate(): int
    {
        $rate = config('dte.taxes.iva_rate', 19);

        if ($rate < 1 || $rate > 100) {
            throw new InvalidArgumentException(
                "The dte.taxes.iva_rate configuration must be between 1 and 100. Got: {$rate}"
            );
        }

        return $rate === null ? 19 : (int) $rate;
    }

    /**
     * The configured national IVA rate as a decimal.
     */
    public static function ivaDecimal(): float
    {
        return self::ivaRate() / 100;
    }
}
