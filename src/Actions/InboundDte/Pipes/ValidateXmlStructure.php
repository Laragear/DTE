<?php

namespace Laragear\Dte\Actions\InboundDte\Pipes;

use Closure;
use Laragear\Dte\Actions\InboundDte\InboundDteData;
use Laragear\Dte\Xml\XmlValidator;

class ValidateXmlStructure
{
    /**
     * Create a new Validate Xml Structure instance.
     */
    public function __construct(
        protected XmlValidator $validator,
    ) {
        //
    }

    /**
     * Handle the incoming Inbound DTE.
     *
     * @param  Closure(InboundDteData):InboundDteData  $next
     */
    public function handle(InboundDteData $data, Closure $next): InboundDteData
    {
        $this->validator->validate($data->email->xmlAttachment);

        return $next($data);
    }
}
