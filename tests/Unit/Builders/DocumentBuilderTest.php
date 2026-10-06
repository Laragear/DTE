<?php

namespace Tests\Unit\Builders;

use DateTimeImmutable;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Laragear\Dte\Actions\CompileDte\Compile;
use Laragear\Dte\Builders\DocumentBuilder;
use Laragear\Dte\Builders\InvoiceBuilder;
use Laragear\Dte\Builders\ReceiptBuilder;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Contracts\Issuable;
use Laragear\Dte\Contracts\Receivable;
use Laragear\Dte\Data\CompanyData;
use Laragear\Dte\Data\IssuerData;
use Laragear\Dte\Data\Item;
use Laragear\Dte\Data\PaymentTermData;
use Laragear\Dte\Data\ReceiverData;
use Laragear\Dte\Data\ReferenceData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Events\DteCreated;
use Laragear\Dte\Events\DteCreating;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Dte\Services\DteLifecycleService;
use Laragear\Rut\Facades\Generator;
use Laragear\Rut\Rut;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use OverflowException;
use Override;
use RuntimeException;
use Tests\DatabaseTestCase;
use Tests\Unit\Builders\Fixtures\BuilderFixture;

class DocumentBuilderTest extends DatabaseTestCase
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

    /*
     |--------------------------------------------------------------------------
     | Happy Paths
     |--------------------------------------------------------------------------
     */

    /**
     * Assert the initial persisted document state.
     */
    protected function assertPendingDocument(SiiDte $dte, string $issuer, string $receiver): void
    {
        static::assertTrue($dte->exists);
        static::assertTrue($dte->payload->exists);
        static::assertSame(DteStatus::Pending, $dte->status);
        static::assertNull($dte->folio);
        static::assertSame(
            ['taxes' => [], 'net' => 1800, 'exempt' => 0, 'tax' => 342, 'total' => 2142, 'non_billable' => 0],
            $dte->payload->header_totals->toArray(),
        );
        $issuer = Rut::parse($issuer);
        $receiver = Rut::parse($receiver);
        $this->assertDatabaseHas('sii_dtes', [
            'issuer_num' => $issuer->num,
            'issuer_vd' => $issuer->vd,
            'receiver_num' => $receiver->num,
            'receiver_vd' => $receiver->vd,
            'document_type' => DteType::Invoice->value,
            'amount_total' => 2142,
            'status' => DteStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('sii_dte_payloads', ['sii_dte_id' => $dte->getKey()]);
    }

    public function test_fluently_creates_a_pending_document_raw_payload_and_queues_compilation(): void
    {
        $queue = Queue::fake();

        $events = Event::fake([DteCreating::class, DteCreated::class]);
        $builder = $this->app->make(InvoiceBuilder::class);
        $issuer = BuilderFixture::issuer();
        $receiver = BuilderFixture::receiver();
        $date = new DateTimeImmutable('2026-08-13');

        static::assertSame($builder, $builder->issuedBy($issuer));
        static::assertSame($builder, $builder->receivedBy($receiver));
        static::assertSame($builder, $builder->issuedOn($date));
        static::assertSame($builder, $builder->addItem(BuilderFixture::item()));

        $this
            ->mock(Compile::class)
            ->expects('forDte')
            ->withArgs(function (SiiDte $dte) use ($receiver): bool {
                return $dte->receiver_rut->isEqual($receiver->rut);
            });

        $dte = $builder->build();

        static::assertPendingDocument($dte, $issuer->rut->formatRaw(), $receiver->rut->formatRaw());
        static::assertSame('Consulting service', $dte->payload->detail_items['items'][0]['name']);
        static::assertSame('2026-08-13', $dte->payload->header_id_doc['issued_on']);
        $events->assertDispatched(DteCreating::class, fn(DteCreating $event): bool => $event->builder === $builder);
        $events->assertDispatched(DteCreated::class, fn(DteCreated $event): bool => $event->dte->is($dte));

        $queue->assertPushed(QueuedCommand::class, function (QueuedCommand $job) {
            $this->app->call($job->handle(...));

            return true;
        });
    }

    public function test_can_build_document_sync(): void
    {
        $queue = Queue::fake();

        $this
            ->mock(Compile::class)
            ->expects('forDte')
            ->withArgs(function (SiiDte $dte) {
                $dte->status = DteStatus::Outbox;

                return true;
            })
            ->andReturnUsing(static fn(SiiDte $dte): SiiDte => $dte);

        $dte = $this->app
            ->make(ReceiptBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->addItem(BuilderFixture::item())
            ->buildSync();

        static::assertSame(DteStatus::Outbox, $dte->status);

        $queue->assertNothingPushed();
    }

    public function test_send_builds_and_sends_exclusive_envelope(): void
    {
        Queue::fake();

        $dte = SiiDte::factory()->create(['status' => DteStatus::Outbox]);
        $envelope = SiiDteEnvelope::factory()->make();

        $this->mock(DteLifecycleService::class, function (MockInterface $mock) use ($dte, $envelope): void {
            $mock->expects('persist')->once()->andReturn($dte);
            $mock->expects('compile')->once()->andReturn($dte);
            $mock->expects('send')->once()
                ->withArgs(static fn(SiiDte $sent, mixed $sync): bool => $sent->is($dte) && $sync === false)
                ->andReturn($envelope);
        });

        $result = $this->app
            ->make(ReceiptBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->addItem(BuilderFixture::item())
            ->send();

        static::assertTrue($result->is($envelope));
    }

    public function test_send_sync_builds_and_sends_synchronously(): void
    {
        Queue::fake();

        $dte = SiiDte::factory()->create(['status' => DteStatus::Outbox]);
        $envelope = SiiDteEnvelope::factory()->make();

        $this->mock(DteLifecycleService::class, function (MockInterface $mock) use ($dte, $envelope): void {
            $mock->expects('persist')->once()->andReturn($dte);
            $mock->expects('compile')->once()->andReturn($dte);
            $mock->expects('send')->once()
                ->withArgs(static fn(SiiDte $sent, mixed $sync): bool => $sent->is($dte) && $sync === true)
                ->andReturn($envelope);
        });

        $result = $this->app
            ->make(ReceiptBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->addItem(BuilderFixture::item())
            ->sendSync();

        static::assertTrue($result->is($envelope));
    }

    public function test_can_get_the_receiver_when_configured(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);

        static::assertNull($builder->receiver());

        $receiver = BuilderFixture::receiver();

        $builder->receivedBy($receiver);

        static::assertSame($receiver, $builder->receiver());
    }

    public function test_issuable_as_issuer(): void
    {
        $fixture = BuilderFixture::issuer();

        $issuable = Mockery::mock(Issuable::class, function (MockInterface $mock) use ($fixture): void {
            $mock->expects('toIssuer')->andReturn($fixture);
        });

        $builder = $this->app->make(InvoiceBuilder::class);

        $builder->issuedBy($issuable);

        static::assertSame($fixture, $builder->issuer());
    }

    public function test_receivable_as_receiver(): void
    {
        $fixture = BuilderFixture::receiver();

        $receivable = Mockery::mock(Receivable::class, function (MockInterface $mock) use ($fixture): void {
            $mock->expects('toReceiver')->andReturn($fixture);
        });

        $builder = $this->app->make(InvoiceBuilder::class);

        $builder->receivedBy($receivable);

        static::assertSame($fixture, $builder->receiver());
    }

    public function test_default_references_is_empty(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);
        static::assertSame([], $builder->references());
    }

    public function test_creates_draft_without_queuing_compilation(): void
    {
        $queue = Queue::fake();

        $dte = $this->app
            ->make(InvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver())
            ->addItem(BuilderFixture::item())
            ->draft();

        static::assertSame(DteStatus::Draft, $dte->status);
        static::assertTrue($dte->payload->exists);
        static::assertTrue($dte->isNotReadOnly());

        $queue->assertNothingPushed();
    }

    public function test_draft_rebuild_preserves_draft_status(): void
    {
        Queue::fake();

        $dte = $this->app
            ->make(InvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver())
            ->addItem(BuilderFixture::item())
            ->draft();

        $updated = $this->app
            ->make(InvoiceBuilder::class)
            ->hydrate($dte->refresh())
            ->addItem(BuilderFixture::item())
            ->draft();

        static::assertTrue($updated->is($dte));
        static::assertSame(DteStatus::Draft, $updated->status);
    }

    public function test_build_on_hydrated_draft_promotes_to_pending(): void
    {
        Queue::fake();

        $dte = $this->app
            ->make(InvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver())
            ->addItem(BuilderFixture::item())
            ->draft();

        $built = $this->app
            ->make(InvoiceBuilder::class)
            ->hydrate($dte->refresh())
            ->addItem(BuilderFixture::item())
            ->build();

        static::assertTrue($built->is($dte));
        static::assertSame(DteStatus::Pending, $built->status);
    }

    public function test_build_draft_promotes_to_pending_and_queues_compilation(): void
    {
        $queue = Queue::fake();

        $draft = $this->app
            ->make(InvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver())
            ->addItem(BuilderFixture::item())
            ->draft();

        $dte = $this->app->make(DteLifecycleService::class)->compile($draft);

        static::assertSame(DteStatus::Pending, $dte->refresh()->status);

        $queue->assertPushed(QueuedCommand::class, 1);
    }

    public function test_accumulates_item_taxes_with_same_code(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);
        $builder->issuedBy(BuilderFixture::issuer());
        $builder->receivedBy(BuilderFixture::receiver());

        // Two items with the same tax code (e.g., 14 for VAT, normally 19%)
        $item1 = new Item('test1', 1000, 1, taxes: [14 => 190]);
        $item2 = new Item('test2', 2000, 1, taxes: [14 => 380, 15 => 100]);

        $builder->addItem($item1);
        $builder->addItem($item2);

        $dte = $builder->build();

        // Total tax for code 14 should be 570, code 15 should be 100.
        static::assertSame(570, $dte->taxes[14]);
        static::assertSame(100, $dte->taxes[15]);
    }

    public function test_references_returns_empty_array(): void
    {
        $builder = $this->mock(DocumentBuilder::class)->makePartial();

        static::assertSame([], $builder->references());
    }

    public function test_adds_payment_term_and_serializes(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);
        $builder->issuedBy(BuilderFixture::issuer());
        $builder->receivedBy(BuilderFixture::receiver());
        $builder->addItem(new Item('test', 100, 1));

        $paymentTerm = PaymentTermData::make('2', new DateTimeImmutable('2026-09-13'));
        $builder->addPaymentTerm($paymentTerm);

        $dte = $builder->build();

        static::assertSame('2', $dte->payload->header_id_doc['payment']['condition']);
        static::assertSame('2026-09-13', $dte->payload->header_id_doc['payment']['expiration_date']);
    }

    public function test_throws_when_issuer_is_missing(): void
    {
        $this->app->instance(ConfigurationManager::class, clone $this->app->make(ConfigurationManager::class));
        $this->app->make(ConfigurationManager::class)->setIssuerResolver(null);
        $builder = $this->app
            ->make(InvoiceBuilder::class)
            ->receivedBy(BuilderFixture::receiver())
            ->addItem(BuilderFixture::item());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('No Issuer resolver has been registered.');

        $builder->build();
    }

    public function test_throws_when_receiver_is_missing(): void
    {
        $builder = $this->app
            ->make(InvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->addItem(BuilderFixture::item());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE receiver has not been configured.');

        $builder->build();
    }

    public function test_throws_when_items_are_missing(): void
    {
        $builder = $this->app
            ->make(InvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE must contain at least one item.');

        $builder->build();
    }

    public function test_throws_when_item_price_is_zero(): void
    {
        $builder = $this->app
            ->make(InvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The item unit price must be greater than zero.');

        $builder->addItem(new Item('test', 0, 1));
    }

    public function test_throws_when_item_price_is_negative(): void
    {
        $builder = $this->app
            ->make(InvoiceBuilder::class)
            ->issuedBy(BuilderFixture::issuer())
            ->receivedBy(BuilderFixture::receiver());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The item unit price must not be negative.');

        $builder->addItem(new Item('test', -100, 1));
    }

    public function test_throws_when_discount_is_invalid(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The item discount percentage must be between 0 and 100.');

        $builder->addItem(new Item('test', 100, 1, discountPercentage: 110));
    }

    public function test_throws_when_too_many_items(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);

        for ($i = 0; $i < 60; $i++) {
            $builder->addItem('item', 100);
        }

        $this->expectException(OverflowException::class);
        $this->expectExceptionMessageIs('A DTE cannot contain more than 60 item lines.');

        $builder->addItem('item 61', 100);
    }

    public function test_handles_exempt_documents_and_modifiers_and_references(): void
    {
        $builder = new class extends DocumentBuilder {
            public $mockGlobal = [];

            public $mockItems = [];

            public $typeMock = DteType::InvoiceExempt;

            public function __construct()
            {
            }

            protected function buildDocument(): array
            {
                return [];
            }

            public function documentType(): DteType
            {
                return $this->typeMock;
            }

            public function items(): array
            {
                return $this->mockItems;
            }

            public function globalModifiers(): array
            {
                return $this->mockGlobal;
            }

            public function addItem(Item $item): static
            {
                return $this;
            }

            public function totals(): array
            {
                return ['net' => 1000, 'exempt' => 100, 'tax' => 190];
            }

            public function callValidateSpecific(): void
            {
                $this->validateSpecific();
            }

            public function callCalculatedTotals(): array
            {
                return $this->calculatedTotals();
            }

            public function callReferenceData(ReferenceData $ref): array
            {
                return $this->referenceData($ref);
            }

            public function callReceivedBy(mixed $receiver, mixed $name)
            {
                return $this->receivedBy($receiver, $name);
            }
        };

        $builder->mockGlobal = [
            ['type' => 'D', 'value_type' => '%', 'value' => 10, 'target' => 1],
            ['type' => 'R', 'value_type' => '$', 'value' => 100, 'target' => 2],
        ];

        $item1 = new Item('name', 1, 1, taxes: [15 => 50, 13 => 100]);
        $builder->mockItems = [$item1];

        // Test Exempt Invoice tax
        $totals = $builder->callCalculatedTotals();
        static::assertSame(0, $totals['tax']);

        // Test Regular Invoice tax
        $builder->typeMock = DteType::Invoice;
        $totals2 = $builder->callCalculatedTotals();
        static::assertEquals(209, $totals2['tax']); // 1100 * 0.19

        $ref = new ReferenceData(DteType::Invoice, '123', new DateTimeImmutable, 'test', 1);
        $compiledRef = $builder->callReferenceData($ref);
        static::assertSame(33, $compiledRef['document_type']);
        static::assertSame('123', $compiledRef['folio']);

        $ref2 = ReferenceData::make('33', '123', new DateTimeImmutable, 'test', 1);
        $compiledRef2 = $builder->callReferenceData($ref2);
        static::assertEquals('33', $compiledRef2['document_type']);

        $builder->callReceivedBy('12345678-5', 'Some Receiver');
        $builder->callValidateSpecific();
    }

    public function test_uses_dynamic_issuer_if_not_explicitly_issued_by(): void
    {
        ConfigurationManager::resolveIssuerUsing(function () {
            return IssuerData::make(
                '76.123.456-0',
                'Dynamic Company',
                'Activity',
                '1234',
                'Addr',
                'Com',
                '2025-01-01',
                80,
                telephone: '123456',
            );
        });

        $builder = $this->app->make(InvoiceBuilder::class);

        $issuer = $builder->issuer();

        static::assertEquals('761234560', $issuer->rut->formatRaw());
        static::assertEquals('Dynamic Company', $issuer->name);

        // We can't call protected issuerData, but we can verify it doesn't throw.
    }

    public function test_throws_exception_if_no_issuer_and_no_dynamic_issuer(): void
    {
        $this->app->instance(ConfigurationManager::class, clone $this->app->make(ConfigurationManager::class));

        $this->app->make(ConfigurationManager::class)->setIssuerResolver(null);

        $builder = $this->app->make(InvoiceBuilder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('No Issuer resolver has been registered.');

        $builder->issuer();
    }

    public function test_fails_b2b_receiver_validation_without_required_fields(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);
        $builder->issuedBy(BuilderFixture::issuer());
        $builder->receivedBy(ReceiverData::make(Generator::asCompanies()->makeOne(),
            'Company')); // Missing address and commune
        $builder->addItem(BuilderFixture::item());
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('B2B documents require a receiver with a business activity, address, and commune.');
        $builder->build();
    }

    public function test_fails_when_more_than_4_acteco_tags_provided(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);
        $builder->issuedBy(IssuerData::make(
            Generator::asCompanies()->makeOne(),
            'Example Company LLC',
            'Software services',
            [1, 2, 3, 4, 5], // 5 acteco
            'Main Street 123',
            'Santiago',
            '2025-01-01',
            80,
            'Santiago',
        ));
        $builder->receivedBy(BuilderFixture::receiver());
        $builder->addItem(BuilderFixture::item());
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The maximum number of Acteco tags allowed is 4.');
        $builder->build();
    }

    public function test_receiver_data_returns_null_if_no_receiver(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);
        $closure = function () {
            return $this->receiverData();
        };
        $bound = $closure->bindTo($builder, $builder);
        static::assertNull($bound());
    }

    public function test_receipt_with_receiver_stores_receiver_data_in_payload(): void
    {
        $builder = $this->app->make(ReceiptBuilder::class);
        $builder->issuedBy(BuilderFixture::issuer());
        $builder->receivedBy(BuilderFixture::receiver());
        $builder->addItem(BuilderFixture::item());
        $dte = $builder->build();

        static::assertFalse($dte->payload->header_receiver->isEmpty());
        static::assertSame('Customer Company LLC', $dte->payload->header_receiver['name']);
    }

    public function test_with_net_amount_indicator_sets_the_indicator(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);
        $builder->withNetAmountIndicator(1);

        static::assertSame(1, $builder->netAmountIndicator());
    }

    public function test_with_net_amount_indicator_throws_on_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('IndMntNeto must be 0, 1, or 2.');

        $this->app->make(InvoiceBuilder::class)->withNetAmountIndicator(3);
    }

    public function test_net_amount_indicator_defaults_to_null(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);

        static::assertNull($builder->netAmountIndicator());
    }

    public function test_items_returns_added_items(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);

        $item = BuilderFixture::item();
        $builder->addItem($item);

        static::assertSame([$item], $builder->items());
    }

    public function test_non_billable_amount_setter_and_getter(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);

        static::assertSame(0, $builder->getNonBillableAmount());

        $builder->nonBillableAmount(500);

        static::assertSame(500, $builder->getNonBillableAmount());
    }

    public function test_non_billable_amount_clamps_negative_to_zero(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);

        $builder->nonBillableAmount(-100);

        static::assertSame(0, $builder->getNonBillableAmount());
    }

    public function test_validate_throws_when_totals_are_negative(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The DTE totals cannot be negative.');

        $builder = $this->app->make(InvoiceBuilder::class);
        $builder->issuedBy(BuilderFixture::issuer());
        $builder->receivedBy(BuilderFixture::receiver());
        $builder->addItem(Item::make('Item', 100, quantity: 1));
        $builder->globalDiscount(200);

        $builder->validate();
    }

    public function test_item_discount_amount_is_applied_during_totals(): void
    {
        $builder = $this->app->make(InvoiceBuilder::class);
        $builder->issuedBy(BuilderFixture::issuer());
        $builder->receivedBy(BuilderFixture::receiver());
        $builder->addItem(Item::make('Item', 1000, quantity: 2, discountPercentage: 10));

        $totals = $builder->totals();

        // 1000 * 2 = 2000, discount = 10% of 2000 = 200, net = 1800
        static::assertSame(1800, $totals['net']);
    }
}
