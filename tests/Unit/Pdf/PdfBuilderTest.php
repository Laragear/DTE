<?php

namespace Tests\Unit\Pdf;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Laragear\Dte\Data\PdfData;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDtePayload;
use Laragear\Dte\Pdf\PdfBuilder;
use Laragear\Dte\Pdf\Ted\Pdf417Encoder;
use Laragear\Dte\Proxies\LibxmlProxy;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder as SpatiePdfBuilder;
use Symfony\Component\HttpFoundation\Response;
use Tests\DatabaseTestCase;

class PdfBuilderTest extends DatabaseTestCase
{
    protected Pdf417Encoder $barcode;

    protected PdfBuilder $builder;

    protected SiiDte $dte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->barcode = $this->mock(Pdf417Encoder::class);

        $view = Mockery::mock(View::class);
        $view->expects('render')->zeroOrMoreTimes()->andReturn('<html>HTML CONTENT</html>');

        $this->mock(ViewFactory::class)->expects('make')->zeroOrMoreTimes()->andReturn($view);

        $this->mock(LibxmlProxy::class, static function (MockInterface $mock): void {
            $mock
                ->expects('useInternalErrors')
                ->with(true)
                ->zeroOrMoreTimes()
                ->andReturnUsing('libxml_use_internal_errors');
            $mock->expects('clearErrors')->zeroOrMoreTimes()->andReturnUsing('libxml_clear_errors');
            $mock->expects('useInternalErrors')->zeroOrMoreTimes()->andReturnUsing('libxml_use_internal_errors');
        });

        $this->dte = SiiDte::factory()
            ->has(SiiDtePayload::factory([
                'xml' => '<DTE version="1.0"><Documento><TED version="1.0">TEST_TED</TED></Documento></DTE>',
            ]), 'payload')
            ->create([
                'issuer_rut' => '76543210-K',
                'document_type' => DteType::Invoice,
                'folio' => 1234,
                'created_at' => Carbon::parse('2026-05-01 19:32:54'),
            ]);

        Pdf::fake();

        $this->builder = $this->app->make(PdfBuilder::class)->forDte($this->dte);
    }

    public static function providesInvalidPayloads(): iterable
    {
        return [
            'Missing Payload' => [
                ['payload' => null],
                'The DTE must have an XML payload to generate a PDF.',
            ],
            'Invalid XML' => [
                ['payload' => ['xml' => 'invalid xml']],
                'The XML payload is invalid.',
            ],
            'Missing TED' => [
                ['payload' => ['xml' => '<DTE><Documento></Documento></DTE>']],
                'The XML payload does not contain a TED element.',
            ],
        ];
    }

    public function test_generates_pdf_and_stores_it(): void
    {
        Storage::fake('local');

        $this->config(['dte.pdf.disk' => 'local', 'dte.pdf.prefix' => 'dte']);

        $this->barcode
            ->expects('generate')
            ->with('<TED version="1.0">TEST_TED</TED>')
            ->andReturn('data:image/png;base64,barcode');

        $data = $this->builder->generate();

        static::assertInstanceOf(PdfData::class, $data);
        static::assertSame('local', $data->disk);
        static::assertSame('dte/76543210-K_33_1234_2026-05-01_193254.pdf', $data->path);

        Storage::disk('local')->assertExists('dte/76543210-K_33_1234_2026-05-01_193254.pdf');
    }

    public function test_does_not_overwrite_existing_pdf(): void
    {
        Storage::fake('local');

        $this->config(['dte.pdf.disk' => 'local', 'dte.pdf.prefix' => 'dte']);

        Storage::disk('local')->put('dte/76543210-K_33_1234_2026-05-01_193254.pdf', 'existing');

        $this->barcode->expects('generate')->never();

        $data = $this->builder->generate();

        static::assertSame('existing', Storage::disk('local')->get($data->path));
    }

    public function test_forces_overwrite(): void
    {
        Storage::fake('local');

        $this->config(['dte.pdf.disk' => 'local', 'dte.pdf.prefix' => 'dte']);

        Storage::disk('local')->put('dte/76543210-K_33_1234_2026-05-01_193254.pdf', 'existing');

        $this->barcode
            ->expects('generate')
            ->with('<TED version="1.0">TEST_TED</TED>')
            ->andReturn('data:image/png;base64,barcode');

        $data = $this->builder->force()->generate();

        static::assertNotSame('existing', Storage::disk('local')->get($data->path));
    }

    public function test_binary(): void
    {
        $this->barcode
            ->expects('generate')
            ->with('<TED version="1.0">TEST_TED</TED>')
            ->andReturn('data:image/png;base64,barcode');

        $content = $this->builder->binary();
        static::assertIsString($content);
    }

    public function test_url_and_temporary_url_and_delete(): void
    {
        Storage::fake('local');
        $this->config(['dte.pdf.disk' => 'local', 'dte.pdf.prefix' => 'dte']);

        $this->barcode->allows('generate')->andReturn('data:image/png;base64,barcode');
        static::assertIsString($this->builder->url());
        static::assertIsString($this->builder->temporaryUrl(now()->addMinutes(5)));
        static::assertTrue($this->builder->delete());
    }

    public function test_customize_and_download_and_response(): void
    {
        $this->barcode->allows('generate')->andReturn('data:image/png;base64,barcode');

        $this->builder->customize(function ($builder) {
            $builder->landscape();
        });

        $download = $this->builder->download('file.pdf');
        static::assertInstanceOf(SpatiePdfBuilder::class, $download);

        $response = $this->builder->toResponse(Request::create('/'));

        static::assertInstanceOf(Response::class, $response);
    }

    public function test_can_set_disk_and_path(): void
    {
        Storage::fake('s3');

        $this->barcode->allows('generate')->andReturn('data:image/png;base64,barcode');

        $data = $this->builder->disk('s3')->as('custom_folder/custom_name.pdf')->generate();

        static::assertSame('s3', $data->disk);
        static::assertSame('custom_folder/custom_name.pdf', $data->path);

        Storage::disk('s3')->assertExists('custom_folder/custom_name.pdf');
    }

    /**
     * @param  array<string, mixed>  $state
     */
    #[DataProvider('providesInvalidPayloads')]
    public function test_rejects_invalid_payloads(array $state, string $message): void
    {
        $dte = SiiDte::factory()->make();

        if (isset($state['payload'])) {
            $payload = SiiDtePayload::factory()->make($state['payload']);
            $dte->setRelation('payload', $payload);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs($message);

        $this->app->make(PdfBuilder::class)->forDte($dte)->binary();
    }

    public function test_binary_returns_content_from_real_spatie_builder(): void
    {
        $mock = Mockery::mock(SpatiePdfBuilder::class)->makePartial();
        $mock->expects('generatePdfContent')->andReturn('real-pdf-content');

        Pdf::swap($mock);

        Pdf::expects('html')->andReturn($mock);
        $mock->expects('driver')->andReturn($mock);
        $mock->expects('format')->andReturn($mock);

        $this->barcode->allows('generate')->andReturn('data:image/png;base64,barcode');

        $content = $this->builder->binary();
        static::assertSame('real-pdf-content', $content);
    }

    public function test_ted_columns_sets_encoder_columns(): void
    {
        $this->barcode
            ->expects('setColumns')
            ->with(15)
            ->once();

        $this->barcode
            ->expects('generate')
            ->andReturn('data:image/png;base64,barcode');

        $this->builder->tedColumns(15)->binary();
    }

    public function test_ted_error_correction_level_sets_encoder_security_level(): void
    {
        $this->barcode
            ->expects('setSecurityLevel')
            ->with(3)
            ->once();

        $this->barcode
            ->expects('generate')
            ->andReturn('data:image/png;base64,barcode');

        $this->builder->tedErrorCorrectionLevel(3)->binary();
    }

    public function test_ted_columns_and_error_correction_are_fluent(): void
    {
        $result = $this->builder->tedColumns(12);

        static::assertSame($this->builder, $result);

        $result = $this->builder->tedErrorCorrectionLevel(7);

        static::assertSame($this->builder, $result);
    }

    public function test_does_not_call_set_columns_when_no_override(): void
    {
        $this->barcode
            ->expects('setColumns')
            ->never();

        $this->barcode
            ->expects('generate')
            ->andReturn('data:image/png;base64,barcode');

        $this->builder->binary();
    }
}
