<?php

namespace Laragear\Dte\Proxies;

use LibXMLError;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;

/** @internal */
class LibxmlProxy
{
    /**
     * Retrieve array of errors.
     *
     * @return LibXMLError[]
     */
    public function getErrors(): array
    {
        return libxml_get_errors();
    }

    /**
     * Disable libxml errors and allow user to fetch error information as needed.
     */
    public function useInternalErrors(?bool $use_errors = null): bool
    {
        return libxml_use_internal_errors($use_errors);
    }

    /**
     * Clear the libxml error buffer.
     */
    public function clearErrors(): void
    {
        libxml_clear_errors();
    }
}
