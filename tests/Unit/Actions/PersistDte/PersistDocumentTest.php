<?php

namespace Tests\Unit\Actions\PersistDte;

use Illuminate\Database\QueryException;
use Laragear\Dte\Actions\PersistDte\DteData;
use Laragear\Dte\Actions\PersistDte\Pipes\PersistDocument;
use Laragear\Dte\Builders\DocumentBuilder;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Enums\ReferenceType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteReference;
use LogicException;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\DatabaseTestCase;

class PersistDocumentTest extends DatabaseTestCase
{
    protected function data(array $attributes, array $payloadData, bool $isUpdate = false): DteData
    {
        $builder = $this->createStub(DocumentBuilder::class);
        $builder->method('attributes')->willReturn($attributes);
        $builder->method('payloadBlocks')->willReturn($payloadData);
        $builder->method('dte')->willReturn(null);

        return new DteData($builder, $attributes, $payloadData, $isUpdate);
    }

    protected function attributes(): array
    {
        return [
            'issuer_rut' => '76192083-9',
            'receiver_rut' => '60803000-K',
            'document_type' => DteType::Invoice,
            'issued_on' => '2026-08-13',
            'amount_net' => 10000,
            'amount_exempt' => 0,
            'amount_taxes' => 1900,
            'amount_total' => 11900,
            'status' => DteStatus::Pending,
        ];
    }

    public function test_persists_document_with_linked_and_external_references(): void
    {
        $target = SiiDte::factory()->create([
            'issuer_rut' => '76192083-9',
            'document_type' => DteType::Invoice,
            'folio' => 100,
        ]);

        $pipe = new PersistDocument($this->app->make(LoggerInterface::class));

        $data = $pipe->handle(
            $this->data($this->attributes(), [
                'references' => ['items' => [
                    [
                        'document_type' => DteType::Invoice->value,
                        'folio' => '100',
                        'date' => '2026-08-01',
                        'reason' => 'Corrige montos',
                        'reference_code' => 1,
                    ],
                    [
                        'document_type' => ReferenceType::PurchaseOrder->value,
                        'folio' => 'PO-1',
                        'date' => '2026-08-01',
                        'reason' => 'Purchase order',
                        'reference_code' => null,
                    ],
                ]],
            ]),
            static fn (DteData $data): DteData => $data,
        );

        static::assertTrue($data->dte->exists);

        $references = SiiDteReference::query()->where('sii_dte_id', $data->dte->getKey())->orderBy('id')->get();

        static::assertCount(2, $references);
        static::assertSame($target->getKey(), $references[0]->target_dte_id);
        static::assertSame(DteType::Invoice, $references[0]->document_type);
        static::assertNull($references[1]->target_dte_id);
        static::assertSame(ReferenceType::PurchaseOrder, $references[1]->document_type);
    }

    public function test_resolves_multiple_reference_targets_with_a_single_query(): void
    {
        $issuer = '76192083-9';

        $invoice = SiiDte::factory()->create([
            'issuer_rut' => $issuer, 'document_type' => DteType::Invoice, 'folio' => 100,
        ]);
        $guide = SiiDte::factory()->create([
            'issuer_rut' => $issuer, 'document_type' => DteType::DispatchGuide, 'folio' => 200,
        ]);

        $pipe = new PersistDocument($this->app->make(LoggerInterface::class));

        $connection = $this->app->make('db')->connection();
        $connection->enableQueryLog();

        $data = $pipe->handle(
            $this->data($this->attributes(), [
                'references' => ['items' => [
                    [
                        'document_type' => DteType::Invoice->value,
                        'folio' => '100',
                        'date' => '2026-08-01',
                        'reason' => 'Anula documento',
                        'reference_code' => 1,
                    ],
                    [
                        'document_type' => DteType::DispatchGuide->value,
                        'folio' => '200',
                        'date' => '2026-08-01',
                        'reason' => 'Corrige texto',
                        'reference_code' => 2,
                    ],
                    [
                        'document_type' => DteType::Invoice->value,
                        'folio' => '999',
                        'date' => '2026-08-01',
                        'reason' => 'Corrige montos',
                        'reference_code' => 3,
                    ],
                    [
                        'document_type' => ReferenceType::PurchaseOrder->value,
                        'folio' => 'PO-1',
                        'date' => '2026-08-01',
                        'reason' => 'Purchase order',
                        'reference_code' => null,
                    ],
                ]],
            ]),
            static fn (DteData $data): DteData => $data,
        );

        $references = SiiDteReference::query()->where('sii_dte_id', $data->dte->getKey())->orderBy('id')->get();

        static::assertCount(4, $references);
        static::assertSame($invoice->getKey(), $references[0]->target_dte_id);
        static::assertSame($guide->getKey(), $references[1]->target_dte_id);
        static::assertNull($references[2]->target_dte_id);
        static::assertNull($references[3]->target_dte_id);

        // The targets are resolved with one query, not one per reference. The
        // closing quote excludes the other "sii_dte_*" tables from the match.
        $selects = array_filter(
            $connection->getQueryLog(),
            static fn (array $entry): bool => str_starts_with(mb_strtolower($entry['query']), 'select')
                && str_contains($entry['query'], '"sii_dtes"'),
        );

        static::assertCount(1, $selects);
    }

    public function test_failure_logs_and_rolls_back(): void
    {
        $this
            ->mock(LoggerInterface::class)
            ->shouldReceive('error')
            ->once()
            ->with(
                'DTE persistence failed, rolling back to the pre-persist state.',
                Mockery::on(static fn (array $context): bool => $context['flow'] === 'dte-persist'
                    && $context['operation'] === 'create'
                    && $context['exception'] === QueryException::class),
            );

        $pipe = new PersistDocument($this->app->make(LoggerInterface::class));

        try {
            $pipe->handle(
                $this->data([], []),
                static fn (DteData $data): DteData => $data,
            );

            static::fail('Expected persistence to fail.');
        } catch (QueryException) {
            //
        }

        static::assertDatabaseCount('sii_dtes', 0);
    }

    public function test_refuses_to_update_a_document_that_was_not_hydrated(): void
    {
        $log = $this->mock(LoggerInterface::class);
        $log->expects('error')->once()->withArgs(static fn (string $message, array $context): bool => $context['flow'] === 'dte-persist'
            && $context['operation'] === 'update'
            && $context['exception'] === LogicException::class
        );

        $pipe = new PersistDocument($log);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot update a document that has not been hydrated.');

        $pipe->handle(
            $this->data($this->attributes(), [], isUpdate: true),
            static fn (DteData $data): DteData => $data,
        );
    }
}
