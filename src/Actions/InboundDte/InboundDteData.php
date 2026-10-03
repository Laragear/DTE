<?php

namespace Laragear\Dte\Actions\InboundDte;

use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Models\SiiInterchangeLog;
use SimpleXMLElement;

/**
 * Context object flowing through the inbound DTE processing pipeline.
 */
class InboundDteData
{
    /**
     * Create a new Inbound Dte Data instance.
     */
    public function __construct(
        public readonly InboundEmailData $email,
        public ?SimpleXMLElement $xml = null,
        public ?string $rootName = null,
        public ?SiiInterchangeLog $log = null,
    ) {
        //
    }
}
