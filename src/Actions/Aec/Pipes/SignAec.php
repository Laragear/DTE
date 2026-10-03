<?php

namespace Laragear\Dte\Actions\Aec\Pipes;

use Closure;
use Laragear\Dte\Actions\Aec\AecData;
use Laragear\Dte\Xml\XmlSigner;

class SignAec
{
    /**
     * Create a new Sign AEC instance.
     */
    public function __construct(
        protected XmlSigner $signer,
    ) {
        //
    }

    /**
     * Handle the incoming AEC Data.
     *
     * @param  Closure(AecData): AecData  $next
     */
    public function handle(AecData $data, Closure $next): AecData
    {
        $data->signedXml = $this->signer->signString(
            $data->xmlString,
            $data->certificate,
            [$data->dtecID, $data->cessionID, $data->aecID],
        );

        return $next($data);
    }
}
