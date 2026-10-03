<?php

namespace Tests\Unit\Actions\CompileDte\Pipes;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Actions\CompileDte\Pipes\ApplyDigitalSignature;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Dte\Xml\XmlValidator;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use Mockery;
use RuntimeException;
use Tests\DatabaseTestCase;

class ApplyDigitalSignatureTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_apply_digital_signature_signs_and_persists_xml(): void
    {
        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'status' => DteStatus::Building,
        ]);

        $payload = SiiDtePayload::factory()->create(['sii_dte_id' => $dte->id]);

        $dte->setRelation('payload', $payload);

        $compilation = new Compilation($dte);

        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML('<?xml version="1.0"?><DTE><Documento></Documento></DTE>');
        $compilation->document = $document;

        $certificate = new DigitalCertificate('fake', 'fake');

        $resolver = $this->mock(CertificateResolverInterface::class);
        $resolver->expects('resolve')->andReturn($certificate);

        $signer = $this->mock(XmlSigner::class);
        $this->mock(XmlValidator::class)->allows('verifySignature');
        $signer
            ->expects('sign')
            ->withArgs(function ($element, $cert) use ($certificate) {
                return $element->nodeName === 'Documento' && $cert === $certificate;
            });

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(ApplyDigitalSignature::class)
            ->send($compilation)
            ->assertPassable(function (Compilation $result) use ($dte) {
                return $result->dte->is($dte) && $result->document === null;
            });

        static::assertEquals(DteStatus::Outbox, $dte->status);

        $this->assertDatabaseHas('sii_dte_payloads', [
            'sii_dte_id' => $dte->id,
            'xml' => $document->saveXML(),
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | Sad paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_exception_if_no_certificate(): void
    {
        $compilation = new Compilation(
            SiiDte::factory()->create([
                'issuer_rut' => Rut::parse('11111111-1'),
            ]),
        );

        $this->mock(CertificateResolverInterface::class)->expects('resolve')->andReturnNull();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('No digital certificate is available for the DTE issuer.');

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(ApplyDigitalSignature::class)
            ->send($compilation);
    }

    public function test_throws_exception_if_no_documento_target(): void
    {
        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
        ]);

        $compilation = new Compilation($dte);
        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML('<?xml version="1.0"?><DTE></DTE>');
        $compilation->document = $document;

        $certificate = new DigitalCertificate('fake', 'fake');

        $this
            ->mock(CertificateResolverInterface::class)
            ->expects('resolve')
            ->andReturn($certificate);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('The DTE XML does not contain a Documento element to sign.');

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(ApplyDigitalSignature::class)
            ->send($compilation);
    }

    /*
     |--------------------------------------------------------------------------
     | Angry paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_exception_when_xml_serialization_fails(): void
    {
        // Line 66-67: throws when saveXML returns false
        $dte = SiiDte::factory()->create([
            'issuer_rut' => Rut::parse('11111111-1'),
            'status' => DteStatus::Building,
        ]);

        $payload = SiiDtePayload::factory()->create(['sii_dte_id' => $dte->id]);
        $dte->setRelation('payload', $payload);

        $compilation = new Compilation($dte);

        // Create a document that will fail to serialize
        $document = Mockery::mock(DOMDocument::class);
        $document->expects('saveXML')->andReturn(false);

        $nodeList = Mockery::mock(DOMNodeList::class);
        $nodeList
            ->expects('item')
            ->with(0)
            ->andReturn(new DOMElement('Documento'));

        // Need getElementsByTagName to return a DOMNodeList for target()
        $document
            ->expects('getElementsByTagName')
            ->with('Documento')
            ->andReturn($nodeList);

        $compilation->document = $document;

        $certificate = new DigitalCertificate('fake', 'fake');

        $this
            ->mock(CertificateResolverInterface::class)
            ->expects('resolve')
            ->andReturn($certificate);

        $this->mock(XmlValidator::class)->allows('verifySignature');
        $this->mock(XmlSigner::class)
            ->expects('sign');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unable to serialize the signed DTE XML.');

        $this
            ->pipeline(Compile::class)
            ->isolatePipe(ApplyDigitalSignature::class)
            ->send($compilation);
    }
}
