<?php

namespace Tests\Unit\Actions\InboundDte\Pipes;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laragear\Dte\Actions\InboundDte\InboundDteData;
use Laragear\Dte\Actions\InboundDte\Pipes\ProcessRespuestaDteDocuments;
use Laragear\Dte\Actions\InboundDte\ProcessInboundDte;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Support\XmlDomFactory;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Tests\DatabaseTestCase;

class ProcessRespuestaDteDocumentsTest extends DatabaseTestCase
{
    use InteractsWithPipelines;
    use RefreshDatabase;

    public function test_skips_non_respuesta_dte(): void
    {
        $data = new InboundDteData(
            new InboundEmailData('msg-1', 'a@b.cl', 'Subject', '<EnvioDTE></EnvioDTE>'),
            rootName: 'EnvioDTE',
        );

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ProcessRespuestaDteDocuments::class)
            ->send($data)
            ->assertPassable(function (InboundDteData $result) {
                return $result->rootName === 'EnvioDTE';
            });
    }

    public function test_updates_accepted_status(): void
    {
        $dte = SiiDte::factory()->create([
            'document_type' => DteType::Invoice,
            'issuer_rut' => '76123456-0',
            'folio' => 500,
        ]);

        $xmlString = '<RespuestaDTE><Resultado><Caratula><RutResponde>77123456-9</RutResponde><RutRecibe>76123456-0</RutRecibe></Caratula><ResultadoDTE><TipoDTE>33</TipoDTE><Folio>500</Folio><EstadoDTE>0</EstadoDTE></ResultadoDTE></Resultado></RespuestaDTE>';

        $data = new InboundDteData(
            new InboundEmailData('msg-1', 'a@b.cl', 'Subject', $xmlString),
            xml: $this->app->make(XmlDomFactory::class)->simpleXml($xmlString),
            rootName: 'RespuestaDTE',
        );

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ProcessRespuestaDteDocuments::class)
            ->send($data)
            ->assertPassable(function (InboundDteData $result) use ($dte) {
                $dte->refresh();

                return $result->rootName === 'RespuestaDTE'
                    && $dte->accepted_at !== null
                    && $dte->acknowledged_at !== null;
            });
    }

    public function test_skips_when_no_matching_dte(): void
    {
        $xmlString = '<RespuestaDTE><Resultado><Caratula><RutResponde>77123456-9</RutResponde><RutRecibe>76123456-0</RutRecibe></Caratula><ResultadoDTE><TipoDTE>33</TipoDTE><Folio>99999</Folio><EstadoDTE>0</EstadoDTE></ResultadoDTE></Resultado></RespuestaDTE>';

        $data = new InboundDteData(
            new InboundEmailData('msg-1', 'a@b.cl', 'Subject', $xmlString),
            xml: $this->app->make(XmlDomFactory::class)->simpleXml($xmlString),
            rootName: 'RespuestaDTE',
        );

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ProcessRespuestaDteDocuments::class)
            ->send($data)
            ->assertPassable(function (InboundDteData $result) {
                return $result->rootName === 'RespuestaDTE';
            });
    }

    public function test_sets_rejected_at_for_rcd_status(): void
    {
        $dte = SiiDte::factory()->create([
            'document_type' => DteType::Invoice,
            'issuer_rut' => '76123456-0',
            'folio' => 501,
        ]);

        $xmlString = '<RespuestaDTE><Resultado><Caratula><RutResponde>77123456-9</RutResponde><RutRecibe>76123456-0</RutRecibe></Caratula><ResultadoDTE><TipoDTE>33</TipoDTE><Folio>501</Folio><EstadoDTE>RCD</EstadoDTE></ResultadoDTE></Resultado></RespuestaDTE>';

        $data = new InboundDteData(
            new InboundEmailData('msg-1', 'a@b.cl', 'Subject', $xmlString),
            xml: $this->app->make(XmlDomFactory::class)->simpleXml($xmlString),
            rootName: 'RespuestaDTE',
        );

        $this
            ->pipeline(ProcessInboundDte::class)
            ->isolatePipe(ProcessRespuestaDteDocuments::class)
            ->send($data)
            ->assertPassable(function (InboundDteData $result) use ($dte) {
                $dte->refresh();

                return $result->rootName === 'RespuestaDTE'
                    && $dte->rejected_at !== null
                    && $dte->accepted_at === null
                    && $dte->acknowledged_at !== null;
            });
    }
}
