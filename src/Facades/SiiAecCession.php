<?php

namespace Laragear\Dte\Facades;

use DateTimeImmutable;
use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Builders\AecCessionBuilder;
use Laragear\Dte\Models\SiiDte;
use Laragear\Rut\Rut;

/**
 * @see AecCessionBuilder
 *
 * @method static AecCessionBuilder forDte(SiiDte $dte)
 * @method static AecCessionBuilder to(Rut|string $rut, string $name)
 * @method static AecCessionBuilder address(string $address, string $email)
 * @method static AecCessionBuilder authorizedBy(Rut|string $rut, string $name, string $email)
 * @method static AecCessionBuilder amount(int $amount)
 * @method static AecCessionBuilder dueDate(DateTimeImmutable|string $date)
 * @method static AecCessionBuilder terms(string $terms)
 * @method static \Laragear\Dte\Models\SiiAecCession create()
 */
class SiiAecCession extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return AecCessionBuilder::class;
    }
}
