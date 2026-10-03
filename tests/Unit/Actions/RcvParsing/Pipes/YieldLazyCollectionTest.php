<?php

namespace Tests\Unit\Actions\RcvParsing\Pipes;

use Illuminate\Support\LazyCollection;
use Laragear\Dte\Actions\RcvParsing\Parse;
use Laragear\Dte\Actions\RcvParsing\ParsingContext;
use Laragear\Dte\Actions\RcvParsing\Pipes\YieldLazyCollection;
use Laragear\Dte\Enums\RcvType;
use Laragear\Dte\Proxies\StreamProxy;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use Mockery\MockInterface;
use Tests\TestCase;

class YieldLazyCollectionTest extends TestCase
{
    use InteractsWithPipelines;

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_handles_empty_or_failed_headers(): void
    {
        $stream = tmpfile();

        $mock = $this->mock(StreamProxy::class);
        $mock->allows('fclose');
        $mock->expects('fgetcsv')->with($stream, null, ';')->andReturn(false);

        $context = new ParsingContext('fake', RcvType::Sales, new Rut('22222222', '2'));
        $context->stream = $stream;

        $this
            ->pipeline(Parse::class)
            ->isolatePipe(YieldLazyCollection::class)
            ->send($context);

        static::assertInstanceOf(LazyCollection::class, $context->records);
        static::assertCount(0, clone $context->records);
    }

    public function test_parses_and_yields_valid_rows(): void
    {
        $stream = tmpfile();

        $this->mock(StreamProxy::class, static function (MockInterface $mock) use ($stream): void {
            $mock
                ->expects('fgetcsv')
                ->with($stream, null, ';')
                ->andReturn([
                    'Tipo Doc',
                    'Folio',
                    'Tipo Compra',
                    'Fecha Docto',
                    'Fecha Acuse',
                    'RUT Proveedor',
                    'Rut cliente',
                    'Monto Total',
                ]);

            $row1 = ['33', '100', 'Del Giro', '25-08-2023', '', '11111111-1', '22222222-2', '1500,50'];
            $row2 = ['invalid', '200', 'Del Giro', '25-08-2023', '', '11111111-1', '22222222-2', '2000'];
            $row3 = ['34'];

            $mock->expects('fgetcsv')->with($stream, null, ';')->times(4)->andReturn($row1, $row2, $row3, false);
            $mock->allows('fclose');
        });

        $context = new ParsingContext('fake', RcvType::Purchases, new Rut('22222222', '2'));
        $context->stream = $stream;

        $this
            ->pipeline(Parse::class)
            ->isolatePipe(YieldLazyCollection::class)
            ->send($context);

        $records = clone $context->records;

        static::assertCount(1, $records->values()->all());
    }

    public function test_parses_sales(): void
    {
        $stream = tmpfile();

        $this->mock(StreamProxy::class, static function (MockInterface $mock) use ($stream): void {
            $mock
                ->expects('fgetcsv')
                ->with($stream, null, ';')
                ->andReturn([
                    'Tipo Doc',
                    'Folio',
                    'Tipo Venta',
                    'Fecha Docto',
                    'Fecha Acuse',
                    'RUT Proveedor',
                    'Rut cliente',
                    'Monto total',
                ]);

            $row1 = ['33', '100', 'Venta Libre', '25-08-2023', '25-08-2023', '11111111-1', '22222222-2', '3000,10'];
            $mock->expects('fgetcsv')->with($stream, null, ';')->times(2)->andReturn($row1, false);
            $mock->allows('fclose');
        });

        $context = new ParsingContext('fake', RcvType::Sales, new Rut('11111111', '1'));
        $context->stream = $stream;

        $this
            ->pipeline(Parse::class)
            ->isolatePipe(YieldLazyCollection::class)
            ->send($context);

        static::assertCount(1, $context->records->values()->all());
    }

    public function test_skips_malformed_empty_trailing_eof_summaries(): void
    {
        $stream = tmpfile();

        $this->mock(StreamProxy::class, static function (MockInterface $mock) use ($stream): void {
            $mock
                ->expects('fgetcsv')
                ->with($stream, null, ';')
                ->andReturn([
                    'Tipo Doc',
                    'Folio',
                    'Tipo Venta',
                    'Fecha Docto',
                    'Rut cliente',
                    'Razon Social',
                    'Monto total',
                ]);

            $row1 = ['39', '37278', 'Del Giro', '06-03-2023', '1111111-1', 'PUBLICO GENERAL', '60000'];
            $row2 = [''];
            $row3 = ['Total', '', '', ''];

            $mock->expects('fgetcsv')->with($stream, null, ';')->times(4)->andReturn($row1, $row2, $row3, false);
            $mock->allows('fclose');
        });

        $context = new ParsingContext('fake', RcvType::Sales, new Rut('11111111', '1'));
        $context->stream = $stream;

        $this
            ->pipeline(Parse::class)
            ->isolatePipe(YieldLazyCollection::class)
            ->send($context);

        static::assertCount(1, $context->records->values()->all());
    }
}
