<?php

namespace Tests\Performance;

use Laragear\Dte\Xml\XmlValidator;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

use function str_repeat;

/**
 * Pinpoint performance tests for the Folio range-list internals.
 *
 * These are intentionally excluded from the default test suite because they
 * assert wall-clock timings. Run them explicitly with:
 *
 *     ./vendor/bin/phpunit --filter=FolioPerformanceTest --group=performance
 */
#[Group('performance')]
class ValidatorPerformanceTest extends TestCase
{
    public function test_parse_rejects_xml_over_10mb(): void
    {
        $validator = $this->app->make(XmlValidator::class);

        // A single text node larger than libxml2's default 10MB limit. The
        // LIBXML_PARSEHUGE flag (which lifts this limit) must not be used when
        // parsing inbound third-party XML.
        $huge = str_repeat('a', (10 * 1024 * 1024) + 1);
        $xml = '<?xml version="1.0"?><DTE><Documento ID="F1T33">'.$huge.'</Documento></DTE>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Invalid DTE XML: the document is malformed or empty.');

        $validator->validate($xml);
    }
}
