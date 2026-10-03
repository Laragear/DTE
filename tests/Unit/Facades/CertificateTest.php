<?php

namespace Tests\Unit\Facades;

use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Facades\Certificate;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    public function test_facade_resolves()
    {
        $this->assertInstanceOf(CertificateResolver::class, Certificate::getFacadeRoot());
    }
}
