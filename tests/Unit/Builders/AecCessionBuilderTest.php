<?php

namespace Tests\Unit\Builders;

use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Laragear\Dte\Builders\AecCessionBuilder;
use Laragear\Dte\Enums\AecStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Events\AecCessionCreated;
use Laragear\Dte\Events\AecCessionCreating;
use Laragear\Dte\Models\SiiDte;
use Laragear\Rut\Facades\Generator;
use LogicException;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\DatabaseTestCase;

class AecCessionBuilderTest extends DatabaseTestCase
{
    public function test_creates_cession_model_fluently(): void
    {
        $event = Event::fake([AecCessionCreating::class, AecCessionCreated::class]);

        $dte = $this->dte();

        $builder = $this->app
            ->make(AecCessionBuilder::class)
            ->forDte($dte)
            ->to('76.543.210-K', 'Factoring S.A.')
            ->address('Some Address 123', 'factoring@example.com')
            ->authorizedBy('12.345.678-9', 'John Doe', 'john@example.com')
            ->amount(1000)
            ->dueDate('2026-12-31')
            ->terms('Custom terms');

        $cession = $builder->build();

        static::assertTrue($cession->exists);
        static::assertSame(1, $cession->cession_number);
        static::assertSame('76543210-K', $cession->rut->formatBasic());
        static::assertSame(1000, $cession->amount_total);
        static::assertSame('2026-12-31', $cession->last_due_on->format('Y-m-d'));
        static::assertSame('Custom terms', $cession->terms);
        static::assertNull($cession->xml);
        static::assertSame(AecStatus::Pending, $cession->status);

        static::assertSame('Factoring S.A.', $cession->data['assignee_name']);
        static::assertSame('Some Address 123', $cession->data['assignee_address']);
        static::assertSame('factoring@example.com', $cession->data['assignee_email']);
        static::assertSame('123456789', $cession->data['authorized_signer_rut']);
        static::assertSame('John Doe', $cession->data['authorized_signer_name']);
        static::assertSame('john@example.com', $cession->data['cedent_email']);

        $event->assertDispatched(
            AecCessionCreating::class,
            fn(AecCessionCreating $event) => $event->builder === $builder,
        );

        $event->assertDispatched(
            AecCessionCreated::class,
            fn(AecCessionCreated $event) => $event->cession->is($cession));
    }

    public function test_defaults_amount_to_dte_total(): void
    {
        $dte = $this->dte();

        $cession = $dte
            ->cede()
            ->to('76.543.210-K', 'Factoring S.A.')
            ->address('Some Address 123', 'factoring@example.com')
            ->authorizedBy('12.345.678-9', 'John Doe', 'john@example.com')
            ->build();

        static::assertSame(1190, $cession->amount_total);
    }

    public function test_increments_cession_number(): void
    {
        $dte = $this->dte();

        $cession1 = $dte
            ->cede()
            ->to('76.543.210-K', 'F')
            ->address('A', 'E')
            ->authorizedBy('12.345.678-9', 'N', 'E')
            ->build();
        $cession2 = $dte
            ->cede()
            ->to('76.543.210-K', 'F')
            ->address('A', 'E')
            ->authorizedBy('12.345.678-9', 'N', 'E')
            ->build();

        static::assertSame(1, $cession1->cession_number);
        static::assertSame(2, $cession2->cession_number);
    }

    public function test_fails_if_no_dte_is_set(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('A document must be set to create a cession.');

        $this->app->make(AecCessionBuilder::class)->build();
    }

    public function test_fails_if_assignee_is_missing(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The assignee details (to, address) must be set.');

        $this->app->make(AecCessionBuilder::class)->forDte($this->dte())->build();
    }

    public function test_fails_if_authorized_signer_is_missing(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The authorized signer details (authorizedBy) must be set.');

        $this->app
            ->make(AecCessionBuilder::class)
            ->forDte($this->dte())
            ->to('76.543.210-K', 'Factoring S.A.')
            ->address('Some Address 123', 'factoring@example.com')
            ->build();
    }

    public function test_fails_if_dte_type_is_not_cedable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The DTE type cannot be transferred through an AEC.');

        $this->app
            ->make(AecCessionBuilder::class)
            ->forDte($this->dte(DteType::CreditNote))
            ->to('76.543.210-K', 'Factoring S.A.')
            ->address('Some Address 123', 'factoring@example.com')
            ->authorizedBy('12.345.678-9', 'John Doe', 'john@example.com')
            ->build();
    }

    public function test_fails_if_amount_exceeds_total(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The cession amount cannot exceed the DTE total amount.');

        $this->app
            ->make(AecCessionBuilder::class)
            ->forDte($this->dte())
            ->to('76.543.210-K', 'Factoring S.A.')
            ->address('Some Address 123', 'factoring@example.com')
            ->authorizedBy('12.345.678-9', 'John Doe', 'john@example.com')
            ->amount(1191)
            ->build();
    }

    public function test_create_failure_logs_warning_and_rethrows(): void
    {
        $dte = Mockery::mock($this->dte())->makePartial();

        $relation = Mockery::mock(HasMany::class);
        $relation->shouldReceive('max')->once()->with('cession_number')->andReturn(0);
        $relation->shouldReceive('create')->once()->andThrow(
            new QueryException('testing', 'insert into "sii_aec_cessions"', [], new Exception('DB failure.'))
        );

        $dte->shouldReceive('aecCessions')->twice()->andReturn($relation);

        $this
            ->mock(LoggerInterface::class)
            ->shouldReceive('warning')
            ->once()
            ->with(
                'AEC cession persistence failed.',
                Mockery::on(static fn(array $context): bool => $context['flow'] === 'aec-cession'
                    && $context['dte_id'] === $dte->getKey()
                    && $context['exception'] === QueryException::class),
            );

        $this->expectException(QueryException::class);

        $this->app
            ->make(AecCessionBuilder::class)
            ->forDte($dte)
            ->to('76.543.210-K', 'Factoring S.A.')
            ->address('Some Address 123', 'factoring@example.com')
            ->authorizedBy('12.345.678-9', 'John Doe', 'john@example.com')
            ->build();
    }

    protected function dte(DteType $type = DteType::Invoice): SiiDte
    {
        $dte = SiiDte::create([
            'issuer_rut' => Generator::asCompanies()->makeOne()->formatRaw(),
            'receiver_rut' => Generator::asCompanies()->makeOne()->formatRaw(),
            'document_type' => $type,
            'folio' => 123,
            'issued_on' => '2026-08-01',
            'amount_total' => 1190,
            'amount_net' => 1000,
            'amount_exempt' => 0,
            'amount_taxes' => 190,
        ]);

        $dte->payload()->create([
            'data' => [],
            'xml' => '<DTE></DTE>',
        ]);

        return $dte;
    }
}
