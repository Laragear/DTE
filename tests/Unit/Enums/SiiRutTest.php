<?php

namespace Tests\Unit\Enums;

use Laragear\Dte\Enums\SiiRut;
use PHPUnit\Framework\TestCase;
use function array_column;

class SiiRutTest extends TestCase
{
    public function test_defines_sii_ruts(): void
    {
        static::assertSame(
            [
                'Dummy' => '76.123.456-0',
                'Sii' => '60.803.000-K',
                'Consumer' => '66.666.666-6',
                'Foreign' => '55.555.555-5',
                'Unregistered' => '44.444.444-4',
            ],
            array_column(SiiRut::cases(), 'value', 'name'),
        );
        static::assertSame(SiiRut::Dummy, SiiRut::DEFAULT);
    }

    public function test_converts_to_rut_instance(): void
    {
        $rut = SiiRut::Dummy->toRut();

        static::assertSame(76123456, $rut->num);
        static::assertSame('0', $rut->vd);
    }

    public function test_formats_ruts(): void
    {
        static::assertSame('76123456-0', SiiRut::Dummy->formatBasic());
        static::assertSame('761234560', SiiRut::Dummy->formatRaw());
        static::assertSame('76.123.456-0', SiiRut::Dummy->format());

        static::assertSame('60803000-K', SiiRut::Sii->formatBasic());
        static::assertSame('60803000K', SiiRut::Sii->formatRaw());
        static::assertSame('60.803.000-K', SiiRut::Sii->format());

        static::assertSame('66666666-6', SiiRut::Consumer->formatBasic());
        static::assertSame('666666666', SiiRut::Consumer->formatRaw());
        static::assertSame('66.666.666-6', SiiRut::Consumer->format());

        static::assertSame('55555555-5', SiiRut::Foreign->formatBasic());
        static::assertSame('555555555', SiiRut::Foreign->formatRaw());
        static::assertSame('55.555.555-5', SiiRut::Foreign->format());

        static::assertSame('44444444-4', SiiRut::Unregistered->formatBasic());
        static::assertSame('444444444', SiiRut::Unregistered->formatRaw());
        static::assertSame('44.444.444-4', SiiRut::Unregistered->format());
    }
}
