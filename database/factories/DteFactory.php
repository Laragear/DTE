<?php

namespace Laragear\Dte\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Laragear\Rut\Facades\Generator;
use Laragear\Rut\Rut;

abstract class DteFactory extends Factory
{
    /**
     * Generate a company RUT for model attributes.
     */
    protected function companyRut(): Rut
    {
        return Generator::asCompanies()->makeOne();
    }
}
