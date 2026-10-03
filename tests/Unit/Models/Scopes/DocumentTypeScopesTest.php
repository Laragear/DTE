<?php

namespace Tests\Unit\Models\Scopes;

use Generator;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiInboundDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentTypeScopesTest extends TestCase
{
    /**
     * @return Generator<string, array{class-string, string}>
     */
    public static function models(): Generator
    {
        yield 'CAF' => [SiiCaf::class, 'sii_cafs'];
        yield 'DTE' => [SiiDte::class, 'sii_dtes'];
        yield 'inbound document' => [SiiInboundDocument::class, 'sii_inbound_documents'];
    }

    /** @param  class-string  $model */
    #[DataProvider('models')]
    public function test_models_cast_document_types_to_enum(string $model, string $table): void
    {
        static::assertSame(
            DteType::Invoice,
            (new $model)->setRawAttributes(['document_type' => DteType::Invoice->value])->document_type,
        );
    }
}
