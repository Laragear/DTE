<?php

namespace Tests\Unit\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laragear\Dte\Certificate\DigitalCertificate;
use Laragear\Dte\Facades\Claim;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Rut\Rut;
use Tests\TestCase;

class SiiInboundDocumentTest extends TestCase
{
    public function test_relationships(): void
    {
        $doc = new SiiInboundDocument;

        static::assertInstanceOf(BelongsTo::class, $doc->interchangeLog());
        static::assertInstanceOf(HasOne::class, $doc->payload());
    }

    public function test_claim_methods(): void
    {
        $doc = new SiiInboundDocument;
        $rut = new Rut('12345678', '5');
        $cert = new DigitalCertificate('fake', 'fake');

        Claim::expects('accept')
            ->with($doc, $rut, 'Santiago', $cert, null)
            ->andReturn('accepted_xml');

        Claim::expects('reject')
            ->with($doc, 'Bad amount');

        Claim::expects('rejectGoods')
            ->with($doc, 'Missing items');

        Claim::expects('rejectPartial')
            ->with($doc, 'Partial delivery');

        Claim::expects('confirmGoodsReceipt')
            ->with($doc, 'Received');

        static::assertSame('accepted_xml', $doc->accept($rut, 'Santiago', $cert));
        $doc->reject('Bad amount');
        $doc->rejectGoods('Missing items');
        $doc->rejectPartial('Partial delivery');
        $doc->confirmGoodsReceipt('Received');
    }
}
