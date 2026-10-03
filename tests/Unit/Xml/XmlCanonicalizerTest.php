<?php

namespace Tests\Unit\Xml;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMNode;
use Laragear\Dte\Proxies\LibxmlProxy;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\Dte\Xml\XmlCanonicalizer;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class XmlCanonicalizerTest extends TestCase
{
    protected XmlCanonicalizer $canonicalizer;

    /**
     * Create the canonicalizer under test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->canonicalizer = new XmlCanonicalizer(new XmlDomFactory(new LibxmlProxy), new LibxmlProxy);
    }

    /**
     * Return the value element from the XML fixture.
     */
    protected function valueElement(DOMDocument $document): DOMElement
    {
        $value = $document->documentElement?->firstElementChild;

        return $value instanceof DOMElement
            ? $value
            : throw new RuntimeException('Unable to load the XML test element.');
    }

    public function test_applies_inclusive_w3c_c14n_without_comments(): void
    {
        $xml = '<root z="2" a="1"><!-- hidden --><empty/></root>';

        static::assertSame(
            '<root a="1" z="2"><empty></empty></root>',
            $this->canonicalizer->canonicalize($xml),
        );
    }

    public function test_canonicalizes_a_dom_node(): void
    {
        $document = $this->app->make(XmlDomFactory::class)->document();
        $document->loadXML('<wrapper><value> DTE </value></wrapper>');
        static::assertSame(
            '<value> DTE </value>',
            $this->canonicalizer->canonicalize($this->valueElement($document)),
        );
    }

    public function test_rejects_malformed_xml(): void
    {
        $this->expectException(DOMException::class);
        $this->expectExceptionMessageIs('Unable to parse XML for canonicalization.');

        $this->canonicalizer->canonicalize('<root>');
    }

    public function test_throws_when_canonicalization_fails(): void
    {
        $node = Mockery::mock(DOMNode::class);
        $node->expects('C14N')->andReturn(false);

        $this->expectException(DOMException::class);
        $this->expectExceptionMessageIs('Unable to canonicalize the XML node.');

        $this->canonicalizer->canonicalize($node);
    }
}
