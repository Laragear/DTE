<?php

namespace Tests\Unit\Models\Scopes;

use Illuminate\Database\Eloquent\Model;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiDteEnvelopePayload;
use Laragear\Dte\Models\SiiDtePayload;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RepairsScopesTest extends TestCase
{
    public static function providesRepairsScopes(): iterable
    {
        return [
            'DTE' => [SiiDte::class, 'sii_dtes', 'repairs'],
            'Envelope' => [SiiDteEnvelope::class, 'sii_dte_envelopes', 'repairs'],
            'DTE Payload' => [SiiDtePayload::class, 'sii_dte_payloads', 'sii_response'],
            'Envelope Payload' => [SiiDteEnvelopePayload::class, 'sii_dte_envelope_payloads', 'sii_response'],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('providesRepairsScopes')]
    public function test_sii_model_repairs_scopes(string $model, string $table, string $column): void
    {
        static::assertSame(
            'select * from "'.$table.'" where "'.$column.'" is not null',
            $model::query()->whereHasRepairs()->toRawSql(),
        );

        static::assertSame(
            'select * from "'.$table.'" where "'.$column.'" is null',
            $model::query()->whereDoesntHaveRepairs()->toRawSql(),
        );
    }
}
