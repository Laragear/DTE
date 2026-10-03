<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Database\Schema\Builder;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Models\SiiDte;
use Mockery\MockInterface;
use Tests\DatabaseTestCase;

class PurgeDatabaseCommandTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->config('dte.environment', 'local');
        $this->app->make(EnvironmentResolver::class)->flush();
    }

    public function test_purges_database_with_force(): void
    {
        SiiDte::factory()->create();
        SiiCaf::factory()->create();

        $this->mock(Builder::class, static function (MockInterface $mock): void {
            $mock->expects('disableForeignKeyConstraints');
            $mock->expects('enableForeignKeyConstraints');
        });

        $this
            ->artisan('dte:purge', ['--force' => true])
            ->expectsOutput('All DTE records purged.')
            ->assertSuccessful();

        $this->assertDatabaseCount('sii_dtes', 0);
        $this->assertDatabaseCount('sii_cafs', 0);
    }

    public function test_purges_database_with_confirmation(): void
    {
        SiiDte::factory()->create();

        $this->mock(Builder::class, static function (MockInterface $mock): void {
            $mock->expects('disableForeignKeyConstraints');
            $mock->expects('enableForeignKeyConstraints');
        });

        $this
            ->artisan('dte:purge')
            ->expectsConfirmation('This wipes all documents, envelopes, interchanges, and CAFs. Continue?', 'yes')
            ->expectsOutput('All DTE records purged.')
            ->assertSuccessful();
    }

    public function test_aborts_without_confirmation(): void
    {
        SiiDte::factory()->create();

        $this->mock(Builder::class, static function (MockInterface $mock): void {
            $mock->expects('disableForeignKeyConstraints')->never();
            $mock->expects('enableForeignKeyConstraints')->never();
        });

        $this
            ->artisan('dte:purge')
            ->expectsConfirmation('This wipes all documents, envelopes, interchanges, and CAFs. Continue?', 'no')
            ->assertSuccessful();

        $this->assertDatabaseCount('sii_dtes', 1);
    }

    public function test_rejects_purge_in_testing_environment(): void
    {
        $this->config('dte.environment', 'testing');
        $this->app->make(EnvironmentResolver::class)->flush();

        $this
            ->artisan('dte:purge', ['--force' => true])
            ->expectsOutputToContain('Purging is not permitted in the [testing] environment.')
            ->assertFailed();
    }

    public function test_allows_purge_in_certification_environment(): void
    {
        $this->config('dte.environment', 'certification');
        $this->app->make(EnvironmentResolver::class)->flush();

        SiiDte::factory()->create();

        $this->mock(Builder::class, static function (MockInterface $mock): void {
            $mock->expects('disableForeignKeyConstraints');
            $mock->expects('enableForeignKeyConstraints');
        });

        $this
            ->artisan('dte:purge', ['--force' => true])
            ->expectsOutput('All DTE records purged.')
            ->assertSuccessful();

        $this->assertDatabaseCount('sii_dtes', 0);
    }

    public function test_rejects_purge_in_production_environment(): void
    {
        $this->config('dte.environment', 'production');
        $this->app->make(EnvironmentResolver::class)->flush();

        $this
            ->artisan('dte:purge', ['--force' => true])
            ->expectsOutputToContain('Purging is not permitted in the [production] environment.')
            ->assertFailed();
    }
}
