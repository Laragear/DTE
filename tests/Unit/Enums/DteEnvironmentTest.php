<?php

namespace Tests\Unit\Enums;

use Laragear\Dte\Enums\DteEnvironment;
use Laragear\Dte\Support\SiiEndpoints;
use PHPUnit\Framework\TestCase;
use function array_column;

class DteEnvironmentTest extends TestCase
{
    public function test_defines_environments(): void
    {
        static::assertSame(
            [
                'Local' => 'local',
                'Testing' => 'testing',
                'Certification' => 'certification',
                'Production' => 'production',
            ],
            array_column(DteEnvironment::cases(), 'value', 'name'),
        );
        static::assertSame(DteEnvironment::Local, DteEnvironment::DEFAULT);
    }

    public function test_pairs_environments_with_sii_base_urls(): void
    {
        static::assertNull(DteEnvironment::Local->soapBaseUrl());
        static::assertNull(DteEnvironment::Local->restBaseUrl());

        static::assertNull(DteEnvironment::Testing->soapBaseUrl());
        static::assertNull(DteEnvironment::Testing->restBaseUrl());

        static::assertSame(SiiEndpoints::SOAP_CERTIFICATION, DteEnvironment::Certification->soapBaseUrl());
        static::assertSame(SiiEndpoints::REST_CERTIFICATION, DteEnvironment::Certification->restBaseUrl());

        static::assertSame(SiiEndpoints::SOAP_PRODUCTION, DteEnvironment::Production->soapBaseUrl());
        static::assertSame(SiiEndpoints::REST_PRODUCTION, DteEnvironment::Production->restBaseUrl());
    }

    public function test_environment_helpers(): void
    {
        static::assertTrue(DteEnvironment::Local->isLocal());
        static::assertFalse(DteEnvironment::Local->isTesting());
        static::assertFalse(DteEnvironment::Local->isProduction());
        static::assertTrue(DteEnvironment::Local->isAllowedOnCertification());
        static::assertTrue(DteEnvironment::Local->allowsFakeAssets());

        static::assertFalse(DteEnvironment::Testing->isLocal());
        static::assertTrue(DteEnvironment::Testing->isTesting());
        static::assertFalse(DteEnvironment::Testing->isProduction());
        static::assertFalse(DteEnvironment::Testing->isAllowedOnCertification());
        static::assertTrue(DteEnvironment::Testing->allowsFakeAssets());

        static::assertFalse(DteEnvironment::Production->isLocal());
        static::assertFalse(DteEnvironment::Production->isTesting());
        static::assertTrue(DteEnvironment::Production->isProduction());
        static::assertFalse(DteEnvironment::Production->isAllowedOnCertification());
        static::assertFalse(DteEnvironment::Production->allowsFakeAssets());
    }
}
