<?php

namespace Tests\Feature\Actions\Fixtures;

use Carbon\Carbon;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Facades\SiiCreditNote;
use Laragear\Dte\Facades\SiiDebitNote;
use Laragear\Dte\Facades\SiiInvoice;
use Laragear\Dte\Models\SiiDte;
use Laragear\Rut\Rut;

class GroundTruthSet
{
    /** @var array<int, SiiDte> */
    public static array $compiled = [];

    public const string ISSUER_RUT = '11111111-1';

    public const string ISSUER_NAME = 'EJEMPLO S.A.';

    public const string RECEIVER_RUT = '77777777-7';

    public const string RECEIVER_NAME = 'EMPRESA  LTDA';

    public const string FROZEN = '2020-09-27T18:37:31';

    public const string ISSUED_ON = '2020-09-27';

    public const string RESOLUTION_DATE = '2020-07-31';

    public const int RESOLUTION_NUMBER = 0;

    public const string CAF_33_STUB = 'static/autorizacion33.xml';

    public const string CERTIFICATE_STUB = 'static/user_password_is_password.pfx';

    public const string CERTIFICATE_PASSWORD = 'password';

    public static function expectedFolio(int $case): int
    {
        return match ($case) {
            1, 2, 3, 4 => $case,
            5, 6, 7 => $case - 4,
            8 => 1,
        };
    }

    public static function goldenFilename(int $case): string
    {
        return "ground_truth_dte_case_{$case}.xml";
    }

    public static function resetCompiled(): void
    {
        static::$compiled = [];
    }

    public static function compileCase(int $case, array &$compiled): SiiDte
    {
        if (isset($compiled[$case])) {
            return $compiled[$case];
        }

        $recipe = static::case($case);

        $facade = $recipe['facade'];
        $builder = $facade::issuedBy(static::issuerData())
            ->receivedBy(static::receiverData())
            ->issuedOn(Carbon::make(static::FROZEN, 'America/Santiago')->toDateTimeImmutable());

        foreach ($recipe['items'] as [$name, $unitPrice, $quantity, $discount, $exempt]) {
            $builder->addItem(Item::make(
                $name,
                $unitPrice,
                $quantity,
                discountPercentage: $discount,
                exempt: $exempt,
            ));
        }

        if (isset($recipe['globalDiscount'])) {
            [$value, $isPercent] = $recipe['globalDiscount'];
            $builder->globalDiscount($value, $isPercent);
        }

        if (isset($recipe['reference'])) {
            $targetCase = $recipe['reference']['targetCase'];
            static::compileCase($targetCase, $compiled);
            $targetDte = $compiled[$targetCase];

            $method = $recipe['reference']['method'];
            $reason = $recipe['reference']['reason'];

            $builder->{$method}(
                $targetDte->document_type->value,
                (string) $targetDte->folio,
                $targetDte->issued_on?->toDateTimeImmutable(),
                $reason,
            );
        }

        $dte = $builder->buildSync();
        $compiled[$case] = $dte;

        return $dte;
    }

    public static function case(int $case): array
    {
        return match ($case) {
            1 => [
                'facade' => SiiInvoice::class,
                'items' => [
                    ['Parlantes Multimedia 180W.', 4500.0, 20.0, 0.0, false],
                    ['Mouse Inalambrico PS/2', 5000.0, 1.0, 0.0, false],
                    ['Caja de Diskettes 10 Unidades', 1000.0, 5.0, 0.0, false],
                ],
            ],

            2 => [
                'facade' => SiiInvoice::class,
                'items' => [
                    ['Pañuelo AFECTO', 2966.0, 373.0, 5.0, false],
                    ['ITEM 2 AFECTO', 2027.0, 304.0, 10.0, false],
                ],
            ],

            3 => [
                'facade' => SiiInvoice::class,
                'items' => [
                    ['Pintura B&W AFECTO', 3343.0, 30.0, 0.0, false],
                    ['ITEM 2 AFECTO', 3174.0, 172.0, 0.0, false],
                    ['ITEM 3 SERVICIO EXENTO', 34859.0, 1.0, 0.0, true],
                ],
            ],

            4 => [
                'facade' => SiiInvoice::class,
                'items' => [
                    ['ITEM 1 AFECTO', 2791.0, 169.0, 0.0, false],
                    ['ITEM 2 AFECTO', 2933.0, 72.0, 0.0, false],
                    ['ITEM 3 SERVICIO EXENTO', 6785.0, 2.0, 0.0, true],
                ],
                'globalDiscount' => [11.0, true],
            ],

            5 => [
                'facade' => SiiCreditNote::class,
                'items' => [
                    ['CORRIGE GIRO DEL RECEPTOR', 1.0, 1.0, 0.0, false],
                ],
                'reference' => [
                    'method' => 'amend',
                    'targetCase' => 1,
                    'reason' => 'Corrige giro del receptor',
                ],
            ],

            6 => [
                'facade' => SiiCreditNote::class,
                'items' => [
                    ['Pañuelo AFECTO', 2966.0, 137.0, 0.0, false],
                    ['ITEM 2 AFECTO', 2027.0, 206.0, 0.0, false],
                ],
                'reference' => [
                    'method' => 'discount',
                    'targetCase' => 2,
                    'reason' => 'Devolución de mercaderías',
                ],
            ],

            7 => [
                'facade' => SiiCreditNote::class,
                'items' => [
                    ['ANULA FACTURA', 1.0, 1.0, 0.0, false],
                ],
                'reference' => [
                    'method' => 'annul',
                    'targetCase' => 3,
                    'reason' => 'Anula factura',
                ],
            ],

            8 => [
                'facade' => SiiDebitNote::class,
                'items' => [
                    ['ANULA NOTA DE CREDITO', 1.0, 1.0, 0.0, false],
                ],
                'reference' => [
                    'method' => 'annul',
                    'targetCase' => 5,
                    'reason' => 'Anula nota de crédito electrónica',
                ],
            ],
        };
    }

    public static function issuerData(): IssuerData
    {
        return IssuerData::make(
            rut: Rut::parse(static::ISSUER_RUT),
            name: static::ISSUER_NAME,
            activity: 'Insumos de Computacion',
            activityCode: '31341',
            address: 'Teatinos 120, Piso 4',
            commune: 'Santiago',
            resolutionDate: static::RESOLUTION_DATE,
            resolutionNumber: static::RESOLUTION_NUMBER,
        );
    }

    public static function receiverData(): ReceiverData
    {
        return ReceiverData::make(
            rut: Rut::parse(static::RECEIVER_RUT),
            name: static::RECEIVER_NAME,
            activity: 'COMPUTACION',
            address: 'SAN DIEGO 2222',
            commune: 'LA FLORIDA',
        );
    }
}
