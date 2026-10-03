<?php

namespace Laragear\Dte\Actions\CreateEnvelope\Pipes;

use Closure;
use DOMDocument;
use DOMElement;
use Laragear\Dte\Actions\CreateEnvelope\Assembly;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Xml\XmlSigner;
use Laragear\Dte\Xml\XmlValidator;
use RuntimeException;

class ApplyEnvelopeSignature
{
    /**
     * Create an Apply Envelope Signature pipe instance.
     */
    public function __construct(
        protected CertificateResolverInterface $certificates,
        protected XmlSigner $signer,
        protected XmlValidator $validator,
    ) {
        //
    }

    /**
     * Hande the incoming DTE Envelope Assembly.
     *
     * @param  Closure(Assembly): Assembly  $next
     */
    public function handle(Assembly $assembly, Closure $next): Assembly
    {
        $certificate = $this->certificates->resolve($assembly->envelope->sender_rut);

        if ($certificate === null) {
            throw new RuntimeException('No digital certificate is available for the envelope sender.');
        }

        $assembly->envelope->transitionTo(EnvelopeStatus::Signing);

        $this->signer->sign($this->target($assembly->requireDocument()), $certificate);

        $this->validator->verifySignatureDocument($assembly->requireDocument(), 'SetDoc');

        return $next($assembly);
    }

    /**
     * Return the SetDTE element that receives the envelope signature.
     */
    protected function target(DOMDocument $document): DOMElement
    {
        $target = $document->getElementsByTagName('SetDTE')->item(0);

        if ($target instanceof DOMElement) {
            return $target;
        }

        throw new RuntimeException('The envelope XML does not contain a SetDTE element to sign.');
    }
}
