<?php

namespace Laragear\Dte\Actions\CreateEnvelope\Pipes;

use Closure;
use DOMException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Laragear\Dte\Actions\CreateEnvelope\Assembly;
use Laragear\Dte\Support\XmlDomFactory;
use RuntimeException;

class CanonicalizeEnvelope
{
    /**
     * Create a Canonicalize Envelope pipe instance.
     */
    public function __construct(
        protected Filesystem $file,
        protected XmlDomFactory $xml,
    ) {
        //
    }

    /**
     * Parse the streamed envelope XML into a DOMDocument.
     *
     * @throws DOMException
     */
    public function handle(Assembly $assembly, Closure $next): Assembly
    {
        // No C14N round-trip: the writer's output is already deterministic,
        // and re-canonicalizing would reorder attributes and destroy the
        // byte-exact layout needed for the SII reference format.
        try {
            $xml = $this->file->get($assembly->requirePath());
        } catch (FileNotFoundException $exception) {
            throw new RuntimeException('Unable to read the temporary envelope XML.', previous: $exception);
        }

        $document = $this->xml->document(encoding: XmlDomFactory::ENCODING);

        if (!$document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Unable to parse the envelope XML.');
        }

        $document->encoding = XmlDomFactory::ENCODING;
        $assembly->document = $document;
        $assembly->path = null;

        return $next($assembly);
    }
}
