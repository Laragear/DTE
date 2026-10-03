<?php

namespace Tests\Unit;

use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\IssuerData;
use Laragear\Rut\Rut;
use RuntimeException;
use Tests\TestCase;

class ConfigurationManagerTest extends TestCase
{
    public function test_resolves_issuer_from_callback(): void
    {
        ConfigurationManager::resolveIssuerUsing(function () {
            return IssuerData::make('76.123.456-0', 'Name', 'Activity', '1234', 'Dir', 'Com', '2025-01-01', 80);
        });

        $manager = $this->app->make(ConfigurationManager::class);

        $issuer = $manager->getIssuer();
        static::assertInstanceOf(IssuerData::class, $issuer);
        static::assertEquals('761234560', $issuer->rut->formatRaw());
    }

    public function test_resolves_sender_from_callback(): void
    {
        $issuerRut = Rut::parse('76.123.456-0');

        ConfigurationManager::resolveSenderUsing(function (Rut $issuer) {
            return '12.345.678-5';
        });

        $manager = $this->app->make(ConfigurationManager::class);

        $sender = $manager->getSender($issuerRut);
        static::assertInstanceOf(Rut::class, $sender);
        static::assertEquals('123456785', $sender->formatRaw());
    }

    public function test_throws_when_issuer_not_set(): void
    {
        ConfigurationManager::resolveIssuerUsing(null);
        $manager = $this->app->make(ConfigurationManager::class);
        $this->expectException(RuntimeException::class);
        $manager->getIssuer();
    }

    public function test_throws_when_sender_not_set(): void
    {
        ConfigurationManager::resolveSenderUsing(null);
        $manager = $this->app->make(ConfigurationManager::class);
        $this->expectException(RuntimeException::class);
        $manager->getSender(Rut::parse('76.123.456-0'));
    }

    public function test_has_sender_resolver(): void
    {
        ConfigurationManager::resolveSenderUsing(function () {
        });
        $manager = $this->app->make(ConfigurationManager::class);
        static::assertTrue($manager->hasSenderResolver());
    }
}
