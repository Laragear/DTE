<?php

namespace Laragear\Dte\Actions\CompileDte;

use DOMDocument;
use DOMElement;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDtePayload;
use LogicException;

class Compilation
{
    /**
     * Create a DTE Compilation context.
     */
    public function __construct(
        public readonly SiiDte $dte,
        public ?DOMDocument $document = null,
        public ?DOMElement $ted = null,
        public ?array $cafData = null,
    ) {
        //
    }

    /**
     * Return the current XML document.
     */
    public function requireDocument(): DOMDocument
    {
        return $this->document ?? throw new LogicException('The DTE XML document has not been built.');
    }

    /**
     * Return the generated TED element.
     */
    public function requireTed(): DOMElement
    {
        return $this->ted ?? throw new LogicException('The DTE TED has not been generated.');
    }

    /**
     * Return the cached CafParser output.
     *
     * @return array<string, mixed>
     */
    public function requireCafData(): array
    {
        return $this->cafData ?? throw new LogicException('CAF data not loaded');
    }

    /**
     * Return the persisted DTE payload.
     */
    public function payload(): SiiDtePayload
    {
        return $this->dte->payload ?? throw new LogicException('The DTE payload does not exist.');
    }
}
