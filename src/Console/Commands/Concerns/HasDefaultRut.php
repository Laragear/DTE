<?php

namespace Laragear\Dte\Console\Commands\Concerns;

use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Enums\SiiRut;
use Laragear\Rut\Rut;

trait HasDefaultRut
{
    /**
     * Retrieve the RUT to use for the CAF.
     */
    protected function rut(ConfigurationManager $manager): Rut
    {
        $rut = $this->option('rut')
            ?: $this->issuerRut($manager)
                ?: SiiRut::DEFAULT->value;

        return Rut::parse($rut);
    }

    /**
     * Check if the manager has an issuer resolver and returns its RUT if it exists.
     */
    protected function issuerRut(ConfigurationManager $manager): ?Rut
    {
        return $manager->hasIssuerResolver()
            ? $manager->getIssuer()->rut
            : null;
    }
}
