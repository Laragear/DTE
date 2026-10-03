<?php

namespace Laragear\Dte\Actions\InboundDte\Pipes;

use Closure;
use Laragear\Dte\Actions\InboundDte\InboundDteData;
use Laragear\Dte\Support\XmlDomFactory;
use RuntimeException;

class ParseInboundDocument
{
    /**
     * Create a new Parse Inbound Document instance.
     */
    public function __construct(
        protected XmlDomFactory $xml,
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
        $data->xml = $this->xml->simpleXml($data->email->xmlAttachment);
        $data->rootName = $data->xml->getName();

        if (!in_array($data->rootName, ['EnvioDTE', 'RespuestaDTE'], true)) {
            throw new RuntimeException("Unsupported root XML element for inbound processing: {$data->rootName}");
        }

        return $next($data);
    }
}
