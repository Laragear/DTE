<?php

namespace Laragear\Dte\Actions\CompileDte\Pipes;

use Closure;
use DOMDocument;
use DOMElement;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Dte\Xml\XmlValidator;
use RuntimeException;

class ApplyDigitalSignature
{
    /**
     * Create an Apply Digital Signature pipe instance.
     */
    public function __construct(
        protected CertificateResolverInterface $certificates,
        protected XmlSigner $signer,
        protected XmlValidator $validator,
    ) {
        //
    }

    /**
     * Handle the incoming DTE compilation.
     *
     * @param  Closure(Compilation): Compilation  $next
     */
    public function handle(Compilation $compilation, Closure $next): Compilation
    {
        $certificate = $this->certificates->resolve($compilation->dte->issuer_rut);

        if (!$certificate) {
            throw new RuntimeException('No digital certificate is available for the DTE issuer.');
        }

        $compilation->dte->transitionTo(DteStatus::Signing);

        $this->signer->sign($this->target($compilation->requireDocument()), $certificate);

        $this->persist($compilation);

        return $next($compilation);
    }

    /**
     * Return the document element that receives the signature.
     */
    protected function target(DOMDocument $document): DOMElement
    {
        $target = $document->getElementsByTagName('Documento')->item(0);

        return $target
            ?? throw new RuntimeException('The DTE XML does not contain a Documento element to sign.');
    }

    /**
     * Persist the signed XML and final lifecycle state.
     */
    protected function persist(Compilation $compilation): void
    {
        $xml = $compilation->requireDocument()->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Unable to serialize the signed DTE XML.');
        }

        $this->validator->verifySignature($xml);

        $compilation->payload()->forceFill(['xml' => $xml])->save();

        $compilation->dte->transitionTo(DteStatus::Outbox);

        $compilation->document = null;
    }
}
