<?php

namespace Tests\Unit\Models;

use Illuminate\Database\QueryException;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Enums\IecvStatus;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiIecv;
use Laragear\Rut\Rut;
use LogicException;
use Tests\DatabaseTestCase;

class SiiIecvTest extends DatabaseTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Happy paths
    |--------------------------------------------------------------------------
    */

    public function test_factory_creates_a_book(): void
    {
        $book = SiiIecv::factory()->create();

        static::assertSame(IecvStatus::Pending, $book->status);
        static::assertSame(IecvType::Sales, $book->type);
        static::assertNull($book->track_id);
    }

    public function test_casts_type_and_status_enums(): void
    {
        $book = SiiIecv::factory()->create(['type' => IecvType::Purchases]);

        static::assertInstanceOf(IecvType::class, $book->type);
        static::assertSame(IecvType::Purchases, $book->type);
    }

    public function test_casts_errors_to_array(): void
    {
        $book = SiiIecv::factory()->create([
            'status' => IecvStatus::Rejected,
            'errors' => ['RSC', 'bad schema'],
        ]);

        static::assertSame(['RSC', 'bad schema'], $book->fresh()->errors);
    }

    public function test_transitions_to_accepted_status(): void
    {
        $book = SiiIecv::factory()->uploaded()->create();

        $book->transitionTo(IecvStatus::Accepted);

        static::assertSame(IecvStatus::Accepted, $book->fresh()->status);
    }

    public function test_has_many_documents(): void
    {
        $book = SiiIecv::factory()->create();
        $dte = SiiDte::factory()->create(['sii_iecv_id' => $book->getKey()]);

        static::assertCount(1, $book->dtes);
        static::assertTrue($book->dtes->first()->is($dte));
    }

    public function test_document_belongs_to_book(): void
    {
        $book = SiiIecv::factory()->create();
        $dte = SiiDte::factory()->create(['sii_iecv_id' => $book->getKey()]);

        static::assertTrue($dte->iecv->is($book));
    }

    /*
    |--------------------------------------------------------------------------
    | Sad paths
    |--------------------------------------------------------------------------
    */

    public function test_rejects_transition_to_a_different_status_family(): void
    {
        $book = SiiIecv::factory()->create();

        $this->expectException(LogicException::class);

        $book->transitionTo(EnvelopeStatus::Accepted);
    }

    public function test_unique_index_rejects_a_second_book_for_the_same_period(): void
    {
        SiiIecv::factory()->create([
            'issuer_rut' => Rut::parse('76123456-0'),
            'type' => IecvType::Sales,
            'period' => '2026-09',
        ]);

        $this->expectException(QueryException::class);

        SiiIecv::factory()->create([
            'issuer_rut' => Rut::parse('76123456-0'),
            'type' => IecvType::Sales,
            'period' => '2026-09',
        ]);
    }
}
