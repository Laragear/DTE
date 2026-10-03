<?php

namespace Laragear\Dte\Actions\CompileDte\Pipes;

use Closure;
use DOMElement;
use Laragear\Dte\Actions\CompileDte\Compilation;
use RuntimeException;

class ApplyTedToDom
{
    /**
     * Handle the incoming DTE compilation.
     *
     * @param  Closure(Compilation): Compilation  $next
     */
    public function handle(Compilation $compilation, Closure $next): Compilation
    {
        $document = $compilation->requireDocument();
        $documentNode = $document->getElementsByTagName('Documento')->item(0);
        $timestamp = $document->getElementsByTagName('TmstFirma')->item(0);

        if (!$documentNode instanceof DOMElement || !$timestamp instanceof DOMElement) {
            throw new RuntimeException('The DTE XML document cannot receive its TED.');
        }

        $documentNode->insertBefore($compilation->requireTed(), $timestamp);

        $compilation->ted = null;

        return $next($compilation);
    }
}
