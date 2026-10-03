<?php

namespace Tests\Unit\Facades;

use Laragear\Dte\Builders\AecCessionBuilder;
use Laragear\Dte\Facades\SiiAecCession;
use Tests\TestCase;

class SiiAecCessionFacadeTest extends TestCase
{
    public function test_facade_accessor_resolves_aec_cession_builder(): void
    {
        static::assertInstanceOf(AecCessionBuilder::class, SiiAecCession::getFacadeRoot());
    }
}
