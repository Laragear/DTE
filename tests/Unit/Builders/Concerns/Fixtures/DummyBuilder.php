<?php

namespace Tests\Unit\Builders\Concerns\Fixtures;

use Laragear\Dte\Builders\Concerns\HasExemptions;
use Laragear\Dte\Builders\Concerns\HasItems;
use Laragear\Dte\Builders\Concerns\HasReferences;
use Laragear\Dte\Builders\Concerns\HasTransport;

class DummyBuilder
{
    use HasExemptions;
    use HasItems;
    use HasReferences;
    use HasTransport;
}
