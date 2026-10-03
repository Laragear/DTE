<?php

namespace Laragear\Dte\Builders;

use Laragear\Dte\Builders\Concerns\HasCorrections;
use Laragear\Dte\Builders\Concerns\HasItems;
use LogicException;

abstract class NoteBuilder extends DocumentBuilder
{
    use HasCorrections;
    use HasItems;

    /**
     * Ensure the note identifies the corrected document.
     */
    protected function validateSpecific(): void
    {
        $this->validateB2bReceiver();

        if ($this->references() === []) {
            throw new LogicException('A '.$this->documentType()->label().' must contain at least one reference.');
        }
    }
}
