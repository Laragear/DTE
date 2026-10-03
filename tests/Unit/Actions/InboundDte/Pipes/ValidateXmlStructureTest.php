<?php

namespace Tests\Unit\Actions\InboundDte\Pipes;

use Laragear\Dte\Actions\InboundDte\InboundDteData;
use Laragear\Dte\Actions\InboundDte\Pipes\ValidateXmlStructure;
use Laragear\Dte\Actions\InboundDte\ProcessInboundDte;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Xml\XmlValidator;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Tests\DatabaseTestCase;

class ValidateXmlStructureTest extends DatabaseTestCase
{
    use InteractsWithPipelines;

    public function test_validates_xml_and_passes_data_through(): void
    {
        $emailData = new InboundEmailData('msg-1', 'a@b.cl', 'Subject', '<xml></xml>');
        $data = new InboundDteData($emailData);

        $this->mock(XmlValidator::class, function ($mock) use ($emailData): void {
            $mock->expects('validate')->with($emailData->xmlAttachment);
        });

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ValidateXmlStructure::class)
            ->send($data)
            ->assertPassable(function (InboundDteData $result) use ($emailData) {
                return $result->email->messageId === $emailData->messageId;
            });
    }
}
