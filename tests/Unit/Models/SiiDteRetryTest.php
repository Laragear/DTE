<?php

namespace Tests\Unit\Models;

use Generator;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\Queue;
use Laragear\Dte\Builders\CreditNoteBuilder;
use Laragear\Dte\Builders\DebitNoteBuilder;
use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiDte;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;
use Tests\Unit\Builders\Fixtures\BuilderFixture;

class SiiDteRetryTest extends DatabaseTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConfigurationManager::setCompany(fn() => CompanyData::make(
            IssuerData::make(
                '76.123.456-0',
                'Test Company',
                'Software',
                ['620100'],
                'Test Address 123',
                'Santiago',
                '2025-01-01',
                76000,
                'Santiago',
                '+56212345678',
                'test@example.com',
                'Casa Matriz',
            ),
            '76.123.456-0',
        ));
    }

    /**
     * Provide every retryable builder and its SII document type.
     *
     * @return Generator<string, array{class-string, DteType}>
     */
    public static function providesRetryableBuilders(): Generator
    {
        foreach (BuilderFixture::builders() as $name => [$class, $type]) {
            yield $name => [$class, $type];
        }
    }

    /**
     * Persist a valid document using the given builder class.
     *
     * @param  class-string  $class
     */
    protected function createDocument(string $class): SiiDte
    {
        $builder = $this->app
            ->make($class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver())
            ->addItem(BuilderFixture::item());

        if ($class === CreditNoteBuilder::class || $class === DebitNoteBuilder::class) {
            $builder->addReference(BuilderFixture::reference());
        }

        return $builder->build();
    }

    /*
     |--------------------------------------------------------------------------
     | Happy Paths
     |--------------------------------------------------------------------------
     */

    #[DataProvider('providesRetryableBuilders')]
    public function test_retry_hydrates_the_correct_builder_from_the_database_payload(
        string $class,
        DteType $type,
    ): void {
        Queue::fake();

        $dte = $this->createDocument($class);

        $dte->refresh()->load('payload');

        $builder = $dte->retry();

        static::assertInstanceOf($class, $builder);
        static::assertSame($type, $builder->documentType());

        static::assertCount(1, $builder->items());
        static::assertInstanceOf(Item::class, $builder->items()[0]);
        static::assertSame('Consulting service', $builder->items()[0]->name);
        static::assertSame(2.0, $builder->items()[0]->quantity);

        $references = $builder->references();

        if ($class === CreditNoteBuilder::class || $class === DebitNoteBuilder::class) {
            static::assertCount(1, $references);
            static::assertInstanceOf(ReferenceData::class, $references[0]);
            static::assertSame(DteType::Invoice, $references[0]->documentType);
            static::assertSame('100', $references[0]->folio);
        } else {
            static::assertSame([], $references);
        }
    }

    public function test_retry_using_resets_the_model_state_and_queues_compilation(): void
    {
        $queue = Queue::fake();

        $dte = $this->createDocument(InvoiceBuilder::class);

        $dte->forceFill([
            'status' => DteStatus::Rejected,
            'folio' => 123,
            'repairs' => ['code' => 3, 'description' => 'Business data error'],
            'rejected_at' => now(),
            'accepted_at' => now()->subDay(),
            'acknowledged_at' => now()->subDays(2),
        ])->save();

        $dte->payload->update(['xml' => '<DTE/>', 'sii_response' => '{"status":"rejected"}']);

        $dte->retryUsing(function (InvoiceBuilder $builder): void {
            $builder->addItem(Item::make('Extra line', 500));
        });

        $dte->refresh()->load('payload');

        static::assertSame(DteStatus::Pending, $dte->status);
        static::assertNull($dte->repairs);
        static::assertNull($dte->rejected_at);
        static::assertNull($dte->accepted_at);
        static::assertNull($dte->acknowledged_at);
        static::assertSame(123, $dte->folio);
        static::assertNull($dte->payload->xml);
        static::assertNull($dte->payload->sii_response);

        static::assertCount(2, $dte->payload->data['items']);
        static::assertSame('Extra line', $dte->payload->data['items'][1]['name']);
        static::assertSame(2300, $dte->payload->data['totals']['net']);
        static::assertSame(2737, $dte->amount_total);

        $queue->assertPushed(
            QueuedCommand::class,
            fn(QueuedCommand $job): bool => $job->displayName() === 'dte:compile',
        );
    }

    /*
     |--------------------------------------------------------------------------
     | Sad Paths
     |--------------------------------------------------------------------------
     */

    public function test_retry_throws_for_unsupported_document_type(): void
    {
        $dte = SiiDte::factory()->create(['document_type' => DteType::ExemptReceipt]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('DTE type [41] does not support retry.');

        $dte->retry();
    }
}
