<?php

namespace Tests\Unit\Models;

use Illuminate\Database\Eloquent\Model;
use Laragear\Dte\Models\SiiAecCession;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiInboundDocument;
use Laragear\Rut\Facades\Generator;
use Laragear\Rut\Rut;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

class RutAttributesTest extends DatabaseTestCase
{
    public static function providesSingleRutModels(): iterable
    {
        return [
            'CAF' => [SiiCaf::class, 'sii_cafs', 'issuer'],
            'AEC Cession' => [SiiAecCession::class, 'sii_aec_cessions', 'assignee'],
        ];
    }

    public static function providesDualRutModels(): iterable
    {
        return [
            'DTE' => [SiiDte::class, 'sii_dtes', 'issuer_rut', 'receiver_rut', 'issuer', 'receiver'],
            'Envelope' => [SiiDteEnvelope::class, 'sii_dte_envelopes', 'issuer_rut', 'sender_rut', 'issuer', 'sender'],
            'Inbound Document' => [
                SiiInboundDocument::class,
                'sii_inbound_documents',
                'issuer_rut',
                'receiver_rut',
                'issuer',
                'receiver',
            ],
        ];
    }

    /**
     * Assert two model attributes are mapped into RUT objects.
     */
    protected static function assertModelRuts(Rut $actualFirst, Rut $actualSecond, Rut $first, Rut $second): void
    {
        static::assertEquals($first, $actualFirst);
        static::assertEquals($second, $actualSecond);
    }

    /**
     * Assert a RUT was split into its database columns.
     */
    protected function assertDatabaseRut(string $table, string $prefix, Rut $rut): void
    {
        $this->assertDatabaseHas($table, ["{$prefix}_num" => $rut->num, "{$prefix}_vd" => $rut->vd]);
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('providesSingleRutModels')]
    public function test_models_use_rut_trait_with_prefixed_columns(string $model, string $table, string $prefix): void
    {
        $rut = Generator::asCompanies()->makeOne();
        $instance = $model::factory()->createOne(['rut' => $rut->formatBasic()]);

        static::assertEquals($rut, $instance->rut);
        static::assertTrue($model::findRut($rut)->is($instance));
        $this->assertDatabaseRut($table, $prefix, $rut);
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('providesDualRutModels')]
    public function test_models_map_dual_rut_attributes(
        string $model,
        string $table,
        string $firstAttr,
        string $secondAttr,
        string $firstPrefix,
        string $secondPrefix,
    ): void {
        $first = Generator::asCompanies()->makeOne();
        $second = Generator::asCompanies()->makeOne();
        $instance = $model::factory()
            ->createOne([
                $firstAttr => $first,
                $secondAttr => $second->formatBasic(),
            ]);

        static::assertModelRuts($instance->{$firstAttr}, $instance->{$secondAttr}, $first, $second);

        $this->assertDatabaseRut($table, $firstPrefix, $first);
        $this->assertDatabaseRut($table, $secondPrefix, $second);
    }
}
