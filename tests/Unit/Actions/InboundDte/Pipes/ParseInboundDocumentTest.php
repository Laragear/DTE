<?php

namespace Tests\Unit\Actions\InboundDte\Pipes;

use Laragear\Dte\Actions\InboundDte\InboundDteData;
use Laragear\Dte\Actions\InboundDte\Pipes\ParseInboundDocument;
use Laragear\Dte\Actions\InboundDte\ProcessInboundDte;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use RuntimeException;
use Tests\DatabaseTestCase;

class ParseInboundDocumentTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    public function test_parses_xml_and_extracts_root_name(): void
    {
        $emailData = new InboundEmailData(
            'msg-1', 'a@b.cl', 'Subject', '<EnvioDTE><SetDTE></SetDTE></EnvioDTE>'
        );

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ParseInboundDocument::class)
            ->send(new InboundDteData($emailData))
            ->assertPassable(function (InboundDteData $result) {
                return $result->xml !== null
                    && $result->rootName === 'EnvioDTE';
            });
    }

    public function test_throws_for_unsupported_root_element(): void
    {
        $emailData = new InboundEmailData('msg-1', 'a@b.cl', 'Subject', '<Unsupported></Unsupported>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Unsupported root XML element for inbound processing: Unsupported');

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ParseInboundDocument::class)
            ->send(new InboundDteData($emailData))
            ->assertPassable(fn() => false);
    }
}
