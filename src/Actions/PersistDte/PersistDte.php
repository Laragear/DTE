<?php

namespace Laragear\Dte\Actions\PersistDte;

use Illuminate\Pipeline\Pipeline;
use Laragear\Dte\Builders\DocumentBuilder;
use Laragear\Dte\Models\SiiDte;

/**
 * @method DteData thenReturn()
 */
class PersistDte extends Pipeline
{
    /**
     * @var list<class-string>
     */
    protected $pipes = [
        Pipes\ValidateDocument::class,
        Pipes\PersistDocument::class,
    ];

    /**
     * Stores the DTE into the database, returning its model.
     */
    public function handle(DocumentBuilder $builder, bool $isUpdate = false): SiiDte
    {
        $data = new DteData(
            builder: $builder,
            attributes: $builder->attributes(),
            payloadBlocks: $builder->payloadBlocks(),
            isUpdate: $isUpdate,
        );

        return $this->send($data)->thenReturn()->dte;
    }
}
