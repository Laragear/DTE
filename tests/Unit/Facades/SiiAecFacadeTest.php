<?php

namespace Tests\Unit\Facades;

use Laragear\Dte\Actions\Aec\CompileAec;
use Laragear\Dte\Facades\SiiAec;
use Tests\TestCase;

class SiiAecFacadeTest extends TestCase
{
    public function test_facade_accessor_resolves_compile_aec(): void
    {
        static::assertInstanceOf(CompileAec::class, SiiAec::getFacadeRoot());
    }
}
