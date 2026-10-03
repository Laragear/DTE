<?php

namespace Laragear\Dte\Support;

/** @internal */
class CertificateParser
{
    /**
     * Extract the base64-encoded certificate from its PEM representation.
     */
    public function parse(string $certificate): string
    {
        $lines = explode("\n", trim($certificate));
        $b64 = '';

        foreach ($lines as $line) {
            if (!str_contains($line, '-----')) {
                $b64 .= trim($line);
            }
        }

        return $b64;
    }
}
