<?php

namespace Laragear\Dte\Xml;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Laragear\Dte\DteServiceProvider;
use Laragear\Dte\Proxies\LibxmlProxy;
use Laragear\Dte\Support\XmlDomFactory;
use RuntimeException;
use function array_map;
use function implode;
use function trim;

class XsdValidator
{
    /**
     * Create a new XSD Validator instance.
     */
    public function __construct(
        protected Application $app,
        protected Filesystem $file,
        protected XmlDomFactory $xml,
        protected LibxmlProxy $libxml,
    ) {
        //
    }

    /**
     * Validate XML against an XSD schema.
     *
     * @throws RuntimeException if validation fails
     */
    public function validate(string $xml, string $schema): void
    {
        $document = $this->xml->document('', '');
        $previous = $this->libxml->useInternalErrors(true);

        try {
            $document->loadXML($xml, LIBXML_NONET);

            if (!$document->schemaValidate($this->getXsd($schema))) {
                $this->throwInvalidSchemaException();
            }
        } finally {
            $this->libxml->clearErrors();
            $this->libxml->useInternalErrors($previous);
        }
    }

    /**
     * Resolve the XSD path, preferring the application assets.
     */
    protected function getXsd(string $schema): string
    {
        $fullPath = $this->app->resourcePath("xsd/$schema");

        if ($this->file->exists($fullPath)) {
            return $fullPath;
        }

        $fullPath = DteServiceProvider::XSD_ASSETS.'/'.$schema;

        if ($this->file->exists($fullPath)) {
            return $fullPath;
        }

        throw new RuntimeException("XSD schema not found: {$schema}");
    }

    /**
     * Construct the exception with the XSD validation messages.
     */
    protected function throwInvalidSchemaException(): never
    {
        // The XSD didn't validate, so we will construct the message.
        $messages = array_map(static fn($error) => trim($error->message), $this->libxml->getErrors());

        throw new RuntimeException('XSD validation failed: '.implode('; ', $messages));
    }
}
