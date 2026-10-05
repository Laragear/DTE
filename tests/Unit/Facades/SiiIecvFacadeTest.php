<?php

namespace Tests\Unit\Facades;

use Laragear\Dte\Facades\SiiIecv;
use Laragear\Dte\Services\IecvService;
use Tests\TestCase;

class SiiIecvFacadeTest extends TestCase
{
    public function test_facade_accessor_resolves_the_iecv_service(): void
    {
        static::assertInstanceOf(IecvService::class, SiiIecv::getFacadeRoot());
    }
}
