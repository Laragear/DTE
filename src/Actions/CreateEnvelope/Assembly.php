<?php

namespace Laragear\Dte\Actions\CreateEnvelope;

use DOMDocument;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Rut\Rut;
use LogicException;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use XMLWriter;

class Assembly
{
    /**
     * Create an Envelope Assembly context.
     */
    public function __construct(
        public readonly SiiDteEnvelope $envelope,
        public ?Rut $targetReceiverRut = null,
        public readonly bool $ephemeral = false,
        public ?TemporaryDirectory $temporary = null,
        public ?string $path = null,
        public ?XMLWriter $writer = null,
        public ?DOMDocument $document = null,
        public int $expectedDocuments = 0,
        public int $embeddedDocuments = 0,
    ) {
        //
    }

    /**
     * Return the temporary envelope file path.
     */
    public function requirePath(): string
    {
        return $this->path ?? throw new LogicException('The temporary envelope file has not been initialized.');
    }

    /**
     * Return the streaming XML writer.
     */
    public function requireWriter(): XMLWriter
    {
        return $this->writer ?? throw new LogicException('The envelope XML writer has not been initialized.');
    }

    /**
     * Return the assembled envelope document.
     */
    public function requireDocument(): DOMDocument
    {
        return $this->document ?? throw new LogicException('The envelope XML document has not been assembled.');
    }

    /**
     * Release the writer and delete temporary artifacts.
     */
    public function cleanup(): void
    {
        $this->writer?->flush();
        $this->writer = null;
        $this->document = null;
        $this->path = null;
        $this->temporary?->delete();
    }
}
