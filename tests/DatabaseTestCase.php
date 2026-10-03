<?php

namespace Tests;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laragear\Dte\DteServiceProvider;

abstract class DatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Model::unsetEventDispatcher();

        parent::tearDown();
    }

    protected function defineEnvironment($app): void
    {
        $app->afterResolving(ConnectionInterface::class, static function (ConnectionInterface $database): void {
            $database->disableQueryLog();
        });

        $app->make('config')->set('database.connections.testing.pragmas', [
            'cache_size' => '-2000',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(DteServiceProvider::MIGRATIONS);
    }
}
