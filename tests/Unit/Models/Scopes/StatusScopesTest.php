<?php

namespace Tests\Unit\Models\Scopes;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Laragear\Dte\Enums\AecStatus;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Enums\InboundDteStatus;
use Laragear\Dte\Models\SiiAecCession;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Models\SiiInboundDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatusScopesTest extends TestCase
{
    public static function providesStatusScopes(): iterable
    {
        return [
            'DTE' => [SiiDte::class, 'sii_dtes', DteStatus::Building],
            'Envelope' => [SiiDteEnvelope::class, 'sii_dte_envelopes', EnvelopeStatus::Assembling],
            'Inbound Document' => [SiiInboundDocument::class, 'sii_inbound_documents', InboundDteStatus::Received],
            'AEC Cession' => [SiiAecCession::class, 'sii_aec_cessions', AecStatus::Signing],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('providesStatusScopes')]
    public function test_sii_model_status_scopes(string $model, string $table, BackedEnum $customStatus): void
    {
        static::assertSame(
            'select * from "'.$table.'" where "status" = \'pending\'',
            $model::query()->pending()->toRawSql(),
        );
        static::assertSame(
            'select * from "'.$table.'" where "status" = \'accepted\'',
            $model::query()->accepted()->toRawSql(),
        );
        static::assertSame(
            'select * from "'.$table.'" where "status" = \''.$customStatus->value.'\'',
            $model::query()->whereStatus($customStatus)->toRawSql(),
        );
    }
}
