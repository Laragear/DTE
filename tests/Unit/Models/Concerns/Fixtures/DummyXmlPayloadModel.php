<?php

namespace Tests\Unit\Models\Concerns\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Laragear\Dte\Models\Concerns\HasXmlPayload;

class DummyXmlPayloadModel extends Model
{
    use HasXmlPayload;

    protected $guarded = [];
}
