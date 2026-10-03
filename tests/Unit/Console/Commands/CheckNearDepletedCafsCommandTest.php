<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Event;
use Laragear\Dte\Events\CafExpiring;
use Laragear\Dte\Events\CafNearDepleted;
use Laragear\Dte\Models\SiiCaf;
use Tests\DatabaseTestCase;

class CheckNearDepletedCafsCommandTest extends DatabaseTestCase
{
    public function test_does_not_dispatch_event_when_caf_is_above_threshold_and_not_expiring(): void
    {
        $event = Event::fake();

        // Total = 100, Current = 80, Remaining = 21 (21%)
        SiiCaf::factory()->create([
            'folio_from' => 1,
            'folio_to' => 100,
            'folio_current' => 80,
            'expires_on' => now()->addDays(10),
        ]);

        $this->artisan('dte:check-cafs', ['--threshold' => 10])
            ->assertSuccessful();

        $event->assertNotDispatched(CafNearDepleted::class);
        $event->assertNotDispatched(CafExpiring::class);
    }

    public function test_dispatches_event_when_caf_is_near_depleted(): void
    {
        $event = Event::fake();

        $caf = SiiCaf::factory()->create([
            'folio_from' => 1,
            'folio_to' => 100,
            'folio_current' => 92,
            'expires_on' => now()->addDays(10),
        ]);

        $this
            ->artisan('dte:check-cafs', ['--threshold' => 10])
            ->expectsOutput('CAF depletion and expiration check completed.')
            ->assertSuccessful();

        $event->assertDispatched(CafNearDepleted::class, function (CafNearDepleted $event) use ($caf) {
            return $event->caf->id === $caf->id && $event->remainingFolios === 9 && $event->percentageRemaining === 9.0;
        });
        $event->assertNotDispatched(CafExpiring::class);
    }

    public function test_dispatches_event_accounting_for_annulled_folios(): void
    {
        $event = Event::fake();

        $caf = SiiCaf::factory()->create([
            'folio_from' => 1,
            'folio_to' => 100,
            'folio_current' => 92,
            'expires_on' => now()->addDays(10),
        ]);

        // Annul folios 93-100, leaving only folio 92 available (1 remaining).
        $caf->annulFolios([93, [94, 100]]);

        $this
            ->artisan('dte:check-cafs', ['--threshold' => 10])
            ->expectsOutput('CAF depletion and expiration check completed.')
            ->assertSuccessful();

        $event->assertDispatched(CafNearDepleted::class, function (CafNearDepleted $event) use ($caf) {
            return $event->caf->id === $caf->id && $event->remainingFolios === 1 && $event->percentageRemaining === 1.0;
        });
        $event->assertNotDispatched(CafExpiring::class);
    }

    public function test_dispatches_caf_expiring_event_when_expires_in_7_days_or_less(): void
    {
        $event = Event::fake();

        $caf = SiiCaf::factory()->create([
            'folio_from' => 1,
            'folio_to' => 100,
            'folio_current' => 80,
            'expires_on' => now()->addDays(5),
        ]);

        $this->artisan('dte:check-cafs', ['--threshold' => 10])
            ->assertSuccessful();

        $event->assertDispatched(CafExpiring::class, function (CafExpiring $event) use ($caf) {
            return $event->caf->id === $caf->id && $event->daysLeft === 5;
        });
        $event->assertNotDispatched(CafNearDepleted::class);
    }
}
