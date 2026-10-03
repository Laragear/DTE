<?php

namespace Laragear\Dte\Actions\CompileDte\Pipes;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Laragear\Dte\Actions\CompileDte\Compilation;
use Laragear\Dte\Xml\XsdValidator;
use function str_replace;

class XsdValidation
{
    /**
     * Create a new XSD Validation pipe instance.
     */
    public function __construct(
        protected Repository $config,
        protected XsdValidator $validator,
    ) {
        //
    }

    /**
     * Handle the incoming DTE compilation.
     *
     * @param  Closure(Compilation): Compilation  $next
     */
    public function handle(Compilation $compilation, Closure $next): Compilation
    {
        // This pipe only runs when the DTE_XSD_VALIDATION is enabled in config.
        if ($this->config->get('dte.validation.xsd_enabled', false)) {
            $this->validate($compilation);
        }

        return $next($compilation);
    }

    /**
     * Validates the DTE against its corresponding schema.
     */
    protected function validate(Compilation $compilation): void
    {
        // The DTE payload is namespace-free (matching the SII reference
        // format), so we inject a temporary xmlns for XSD validation.
        $dte = $compilation->dte;
        $schema = $dte->document_type->schemaXsd();

        if ($schema && isset($compilation->document)) {
            $xml = $compilation->document->saveXML();
            $namespaced = str_replace(
                '<DTE version="1.0">',
                '<DTE xmlns="http://www.sii.cl/SiiDte" version="1.0">',
                $xml,
            );
            $this->validator->validate($namespaced, $schema);
        }
    }
}
