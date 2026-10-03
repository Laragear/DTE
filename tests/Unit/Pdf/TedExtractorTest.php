<?php

namespace Tests\Unit\Pdf;

use InvalidArgumentException;
use Laragear\Dte\Pdf\TedExtractor;
use Laragear\Dte\Proxies\LibxmlProxy;
use Mockery\MockInterface;
use Tests\DatabaseTestCase;

class TedExtractorTest extends DatabaseTestCase
{
    protected TedExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(LibxmlProxy::class, static function (MockInterface $mock): void {
            $mock
                ->expects('useInternalErrors')
                ->with(true)
                ->zeroOrMoreTimes()
                ->andReturnUsing('libxml_use_internal_errors');
            $mock->expects('clearErrors')->zeroOrMoreTimes()->andReturnUsing('libxml_clear_errors');
            $mock->expects('useInternalErrors')->zeroOrMoreTimes()->andReturnUsing('libxml_use_internal_errors');
        });

        $this->extractor = $this->app->make(TedExtractor::class);
    }

    public function test_extracts_ted_from_valid_xml(): void
    {
        $xml = '<DTE version="1.0"><Documento ID="F1T33"><TED version="1.0">TEST_TED</TED></Documento></DTE>';

        $ted = $this->extractor->extract($xml);

        static::assertStringContainsString('TEST_TED', $ted);
        static::assertStringContainsString('TED', $ted);
    }

    public function test_throws_on_invalid_xml(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The XML payload is invalid.');

        $this->extractor->extract('invalid xml');
    }

    public function test_throws_when_ted_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The XML payload does not contain a TED element.');

        $this->extractor->extract('<DTE version="1.0"><Documento ID="F1T33"></Documento></DTE>');
    }
}
