<?php

namespace Tests\Unit\Actions\RcvParsing\Pipes;

use InvalidArgumentException;
use Laragear\Dte\Actions\RcvParsing\Parse;
use Laragear\Dte\Actions\RcvParsing\ParsingContext;
use Laragear\Dte\Actions\RcvParsing\Pipes\NormalizeToStreamResource;
use Laragear\Dte\Enums\RcvType;
use Laragear\Dte\Proxies\StreamProxy;
use Laragear\MetaTesting\Pipeline\InteractsWithPipelines;
use Laragear\Rut\Rut;
use Mockery;
use RuntimeException;
use SplFileInfo;
use Tests\TestCase;

class NormalizeToStreamResourceTest extends TestCase
{
    use InteractsWithPipelines;

    protected function getContext(): ParsingContext
    {
        return new ParsingContext('source', RcvType::Sales, new Rut('11111111', '1'));
    }

    /*
     |--------------------------------------------------------------------------
     | Happy paths
     |--------------------------------------------------------------------------
     */

    public function test_resolves_string_to_stream_successfully(): void
    {
        $context = $this->getContext();
        $context->source = 'some string';

        $this
            ->pipeline(Parse::class)
            ->isolatePipe(NormalizeToStreamResource::class)
            ->send($context);

        static::assertIsResource($context->stream);
        $contents = stream_get_contents($context->stream);
        static::assertSame('some string', $contents);
    }

    public function test_resolves_spl_file_info_to_stream_successfully(): void
    {
        $csvPath = sys_get_temp_dir().'/dummy-test.csv';

        file_put_contents($csvPath, 'some file content');

        $context = $this->getContext();
        $context->source = new SplFileInfo($csvPath);

        $this
            ->pipeline(Parse::class)
            ->isolatePipe(NormalizeToStreamResource::class)
            ->send($context);

        static::assertIsResource($context->stream);

        $contents = stream_get_contents($context->stream);

        static::assertSame('some file content', $contents);

        if (file_exists($csvPath)) {
            unlink($csvPath);
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Angry paths
     |--------------------------------------------------------------------------
     */

    public function test_throws_if_stream_proxy_fails_from_string(): void
    {
        $this->mock(StreamProxy::class)->expects('fopen')->with('php://temp', 'r+')->andReturn(false);

        $context = $this->getContext();
        $context->source = 'some raw string';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Failed applying stream protocols on the provided RCV file source.');

        $this
            ->pipeline(Parse::class)
            ->isolatePipe(NormalizeToStreamResource::class)
            ->send($context);
    }

    public function test_throws_on_unsupported_format(): void
    {
        $context = $this->getContext();
        $context->source = 12345;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Unsupported parsing source format provided.');

        $this
            ->pipeline(Parse::class)
            ->isolatePipe(NormalizeToStreamResource::class)
            ->send($context);
    }

    public function test_throws_when_spl_file_info_returns_false_path(): void
    {
        $context = $this->getContext();
        $context->source = Mockery::mock(SplFileInfo::class, static function (Mockery\MockInterface $mock): void {
            $mock->expects('getRealPath')->andReturn(false);
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Provided SplFileInfo is invalid or missing.');

        $this
            ->pipeline(Parse::class)
            ->isolatePipe(NormalizeToStreamResource::class)
            ->send($context);
    }
}
