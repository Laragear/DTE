<?php

namespace Tests\Unit\Database;

use Illuminate\Support\Facades\Schema;
use Laragear\Dte\DteServiceProvider;
use Override;
use Tests\TestCase;
use function glob;
use function unlink;

class MigrationsTest extends TestCase
{
    /** @var list<string> */
    protected static array $publishedMigrations = [];

    /**
     * Remove migrations generated with dynamic publication timestamps.
     */
    #[Override]
    public static function tearDownAfterClass(): void
    {
        foreach (static::$publishedMigrations as $migration) {
            unlink($migration);
        }

        static::$publishedMigrations = [];

        parent::tearDownAfterClass();
    }

    public function test_runs_all_published_migrations_with_foreign_keys(): void
    {
        $this->publishMigrations();
        $this->artisan('migrate:fresh')->assertSuccessful();

        static::assertTablesExist();
        static::assertForeignKeysExist();

        $this->artisan('migrate:rollback')->assertSuccessful();
        static::assertTablesDoNotExist();
    }

    /**
     * Publish and remember the timestamped application migrations.
     */
    protected function publishMigrations(): void
    {
        $exitCode = $this->artisan('vendor:publish', [
            '--provider' => DteServiceProvider::class,
            '--tag' => 'migrations',
            '--force' => true,
        ])->run();
        $files = glob($this->app->databasePath('migrations/*_create_sii_*_table.php'));
        static::$publishedMigrations = $files === false ? [] : $files;

        static::assertSame(0, $exitCode);
        static::assertCount(count($this->tables()), static::$publishedMigrations);
    }

    /**
     * Assert that all package tables exist.
     */
    protected function assertTablesExist(): void
    {
        foreach ($this->tables() as $table) {
            static::assertTrue(Schema::hasTable($table), "The [$table] table does not exist.");
        }
    }

    /**
     * Assert that all package tables were removed.
     */
    protected function assertTablesDoNotExist(): void
    {
        foreach ($this->tables() as $table) {
            static::assertFalse(Schema::hasTable($table), "The [$table] table still exists.");
        }
    }

    /**
     * Assert that every relationship has a database foreign key.
     */
    protected function assertForeignKeysExist(): void
    {
        foreach ($this->foreignKeyCounts() as $table => $count) {
            static::assertCount($count, Schema::getForeignKeys($table), "The [$table] foreign keys are incomplete.");
        }
    }

    /**
     * Return the package database tables.
     *
     * @return list<string>
     */
    protected function tables(): array
    {
        return [
            'sii_cafs',
            'sii_dtes',
            'sii_dte_payloads',
            'sii_dte_references',
            'sii_dte_envelopes',
            'sii_dte_envelope_payloads',
            'sii_interchange_logs',
            'sii_inbound_documents',
            'sii_inbound_document_payloads',
            'sii_aec_cessions',
            'sii_iecvs',
        ];
    }

    /**
     * Return the expected foreign key count for each related table.
     *
     * @return array<string, int>
     */
    protected function foreignKeyCounts(): array
    {
        return [
            'sii_dtes' => 3,
            'sii_dte_payloads' => 1,
            'sii_dte_references' => 2,
            'sii_dte_envelope_payloads' => 1,
            'sii_interchange_logs' => 1,
            'sii_inbound_documents' => 1,
            'sii_inbound_document_payloads' => 1,
            'sii_aec_cessions' => 1,
        ];
    }
}
