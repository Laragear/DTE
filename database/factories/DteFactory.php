<?php

namespace Laragear\Dte\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Laragear\Rut\Facades\Generator;
use Laragear\Rut\Rut;

/**
 * @template TModel of Model
 *
 * @extends Factory<TModel>
 */
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
