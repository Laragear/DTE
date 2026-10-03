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
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\DatabaseTestCase;

class PersistDocumentTest extends DatabaseTestCase
{
    protected function data(array $attributes, array $payloadData, bool $isUpdate = false): DteData
    {
        $builder = $this->createStub(DocumentBuilder::class);
        $builder->method('attributes')->willReturn($attributes);
        $builder->method('payloadData')->willReturn($payloadData);
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
                'references' => [
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
                ]
            ]),
            static fn(DteData $data): DteData => $data,
        );

        static::assertTrue($data->dte->exists);

        $references = SiiDteReference::query()->where('sii_dte_id', $data->dte->getKey())->orderBy('id')->get();

        static::assertCount(2, $references);
        static::assertSame($target->getKey(), $references[0]->target_dte_id);
        static::assertSame(DteType::Invoice, $references[0]->document_type);
        static::assertNull($references[1]->target_dte_id);
        static::assertSame(ReferenceType::PurchaseOrder, $references[1]->document_type);
    }

    public function test_failure_logs_and_rolls_back(): void
    {
        $this
            ->mock(LoggerInterface::class)
            ->shouldReceive('error')
            ->once()
            ->with(
                'DTE persistence failed, rolling back to the pre-persist state.',
                Mockery::on(static fn(array $context): bool => $context['flow'] === 'dte-persist'
                    && $context['operation'] === 'create'
                    && $context['exception'] === QueryException::class),
            );

        $pipe = new PersistDocument($this->app->make(LoggerInterface::class));

        try {
            $pipe->handle(
                $this->data([], []),
                static fn(DteData $data): DteData => $data,
            );

            static::fail('Expected persistence to fail.');
        } catch (QueryException) {
            //
        }

        static::assertDatabaseCount('sii_dtes', 0);
    }
}
