<?php

namespace Tests\Unit\Testing;

use DOMDocument;
use Tests\TestCase;

class StubTedTest extends TestCase
{
    public function test_stub_ted_xml_is_valid_xml(): void
    {
        $xml = static::getStub('stub_ted.xml');

        static::assertNotEmpty($xml);

        $doc = new DOMDocument;
        static::assertTrue($doc->loadXML($xml));
    }

    public function test_stub_ted_xml_contains_sii_namespace(): void
    {
        $xml = static::getStub('stub_ted.xml');

        static::assertStringContainsString('xmlns="http://www.sii.cl/SiiDte"', $xml);
    }
}
