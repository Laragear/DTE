<?php

namespace Laragear\Dte\Actions\PersistDte;

use Laragear\Dte\Builders\DocumentBuilder;
use Laragear\Dte\Models\SiiDte;

class DteData
{
    /**
     * Create a new Dte Data instance.
     */
    public function __construct(
        public readonly DocumentBuilder $builder,
        public readonly array $attributes,
        public readonly array $payloadBlocks,
        public bool $isUpdate = false,
        public ?SiiDte $dte = null,
    ) {
        //
    }
}
