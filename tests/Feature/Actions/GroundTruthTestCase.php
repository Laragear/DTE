<?php

namespace Tests\Feature\Actions;

use Carbon\Carbon;
use Laragear\Dte\Caf\CafManager;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Rut\Rut;
use Tests\DatabaseTestCase;
use Tests\Feature\Actions\Fixtures\GroundTruthSet;

abstract class GroundTruthTestCase extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->config('dte.validation.xsd_enabled', true);
        $this->configureIssuer();
        $this->seedCafs();
        $this->freezeClock();
        $this->withGroundTruthCertificate();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        GroundTruthSet::resetCompiled();
        gc_collect_cycles();
    }

    protected function configureIssuer(): void
    {
        ConfigurationManager::setCompany(fn () => CompanyData::make(
            issuer: IssuerData::make(
                Rut::parse(GroundTruthSet::ISSUER_RUT),
                GroundTruthSet::ISSUER_NAME,
                'Insumos de Computacion',
                '31341',
                'Teatinos 120, Piso 4',
                'Santiago',
                GroundTruthSet::RESOLUTION_DATE,
                GroundTruthSet::RESOLUTION_NUMBER,
            ),
            senderRut: Rut::parse(GroundTruthSet::ISSUER_RUT),
        ));
    }

    protected function seedCafs(): void
    {
        $manager = app(CafManager::class);

        foreach ([33, 56, 61] as $type) {
            $manager->store(static::getStub("static/autorizacion{$type}.xml"));
        }
    }

    protected function freezeClock(): void
    {
        $this->travelTo(Carbon::make(GroundTruthSet::FROZEN, 'America/Santiago'));
    }

    protected function withGroundTruthCertificate(): void
    {
        $certificate = new DigitalCertificate(
            static::getStub(GroundTruthSet::CERTIFICATE_STUB),
            GroundTruthSet::CERTIFICATE_PASSWORD,
        );

        $this->mock(CertificateResolverInterface::class, function ($mock) use ($certificate): void {
            $mock->expects('resolve')->zeroOrMoreTimes()->andReturn($certificate);
        });
    }

    protected function assertXmlMatchesGroundTruth(string $stub, string $actual): void
    {
        static::assertSame(static::getStub($stub), $actual, "Ground truth mismatch for [{$stub}].");
    }
}
