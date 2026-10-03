<?php

namespace Tests\Unit\Actions\CreateEnvelope\Pipes;

use Laragear\Dte\Actions\CreateEnvelope\Assembly;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Actions\CreateEnvelope\Pipes\ApplyEnvelopeSignature;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Dte\Xml\XmlValidator;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use RuntimeException;
use Tests\DatabaseTestCase;

class ApplyEnvelopeSignatureTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    protected function makeAssembly(string $xml = '<EnvioDTE><SetDTE></SetDTE></EnvioDTE>'): Assembly
    {
        $envelope = SiiDteEnvelope::factory()->create([
            'sender_rut' => Rut::parse('11111111-1'),
            'status' => EnvelopeStatus::Assembling,
        ]);

        $assembly = new Assembly($envelope);
        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML($xml);
        $assembly->document = $document;

        return $assembly;
    }

    /*
    |--------------------------------------------------------------------------
    | Angry paths
    |--------------------------------------------------------------------------
    */

    public function test_signs_envelope_with_sender_certificate(): void
    {
        $assembly = $this->makeAssembly();
        $certificate = new DigitalCertificate('fake', 'fake');

        $resolver = $this->mock(CertificateResolverInterface::class);
        $resolver->expects('resolve')
            ->andReturn($certificate);

        $signer = $this->mock(XmlSigner::class);
        $this->mock(XmlValidator::class)->allows('verifySignatureDocument');
        $signer->expects('sign')
            ->withArgs(function ($element, $cert) use ($certificate) {
                return $element->nodeName === 'SetDTE' && $cert === $certificate;
            });

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(ApplyEnvelopeSignature::class)
            ->send($assembly)
            ->assertPassable(function (Assembly $result) {
                static::assertEquals(EnvelopeStatus::Signing, $result->envelope->status);

                return true;
            });
    }

    /*
     |--------------------------------------------------------------------------
     | Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_if_certificate_is_missing(): void
    {
        $assembly = $this->makeAssembly();

        $resolver = $this->mock(CertificateResolverInterface::class);
        $resolver->expects('resolve')->andReturnNull();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('No digital certificate is available for the envelope sender.');

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(ApplyEnvelopeSignature::class)
            ->send($assembly);
    }

    public function test_throws_if_set_dte_element_is_missing(): void
    {
        $assembly = $this->makeAssembly('<EnvioDTE><WrongElement></WrongElement></EnvioDTE>');
        $certificate = new DigitalCertificate('fake', 'fake');

        $resolver = $this->mock(CertificateResolverInterface::class);
        $resolver->expects('resolve')->andReturn($certificate);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('The envelope XML does not contain a SetDTE element to sign.');

        $this->pipeline(CreateEnvelope::class)
            ->isolatePipe(ApplyEnvelopeSignature::class)
            ->send($assembly);
    }
}
