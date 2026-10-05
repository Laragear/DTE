<?php

namespace Tests\Unit\Providers;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Foundation\Application;
use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\DteServiceProvider;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Pdf\Ted\Pdf417Encoder;
use Laragear\MetaTesting\InteractsWithServiceProvider;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use ReflectionClass;
use Tests\TestCase;

class DteServiceProviderTest extends TestCase
{
    use InteractsWithServiceProvider;

    public function test_merges_config(): void
    {
        static::assertConfigMerged(DteServiceProvider::CONFIG, 'dte');
    }

    public function test_loads_views(): void
    {
        static::assertHasViews(DteServiceProvider::VIEWS, 'dte');
    }

    public function test_registers_the_environment_resolver_as_a_singleton(): void
    {
        static::assertHasSingletons(EnvironmentResolver::class);
    }

    public function test_registers_the_certificate_manager_as_a_singleton(): void
    {
        static::assertHasSingletons(CertificateResolver::class);

        static::assertSame(
            $this->app->make(CertificateResolver::class),
            $this->app->make(CertificateResolver::class),
        );

        static::assertInstanceOf(
            CertificateResolver::class,
            $this->app->make(CertificateResolverInterface::class),
        );
    }

    public function test_registers_pdf417_encoder(): void
    {
        $encoder = $this->app->make(Pdf417Encoder::class);
        static::assertInstanceOf(Pdf417Encoder::class, $encoder);
    }

    public function test_publishes_config(): void
    {
        static::assertPublishes($this->app->configPath('dte.php'), 'config');
    }

    public function test_publishes_migrations(): void
    {
        static::assertPublishes($this->app->databasePath('migrations'), 'migrations');
    }

    public function test_registers_commands(): void
    {
        $this->assertHasCommand(
            'dte:check-cafs',
            'dte:fetch-mailbox',
            'dte:reject-phantom-invoices',
            'dte:poll-track-status',
            'dte:compile',
            'dte:process-envelope',
            'dte:pack-manual',
            'dte:pack-ready',
            'dte:purge',
        );
    }

    protected function asInRequestMode(Application $app): void
    {
        new ReflectionClass($app)->getProperty('isRunningInConsole')->setValue($app, false);
    }

    #[DefineEnvironment('asInRequestMode')]
    public function test_should_not_register_fake_commands_when_not_in_console(): void
    {
        $commands = $this->app->make(ConsoleKernel::class)->all();

        foreach (['dte:make-fake-cert', 'dte:make-fake-caf'] as $alias) {
            static::assertThat(
                $commands, static::logicalNot(static::arrayHasKey($alias)), "The '$alias' command is registered."
            );
        }
    }

    protected function asProduction(Application $app): void
    {
        $app['config']->set('dte.environment', 'production');
    }

    #[DefineEnvironment('asProduction')]
    public function test_should_not_register_fake_commands_in_production(): void
    {
        $commands = $this->app->make(ConsoleKernel::class)->all();

        foreach (['dte:make-fake-cert', 'dte:make-fake-caf'] as $alias) {
            static::assertThat(
                $commands, static::logicalNot(static::arrayHasKey($alias)),
                "The '$alias' command is registered in production."
            );
        }
    }

    protected function asLocal(Application $app): void
    {
        $app['config']->set('dte.environment', 'local');
    }

    #[DefineEnvironment('asLocal')]
    public function test_should_register_fake_commands_in_local(): void
    {
        $commands = $this->app->make(ConsoleKernel::class)->all();

        foreach (['dte:make-fake-cert', 'dte:make-fake-caf'] as $alias) {
            static::assertArrayHasKey($alias, $commands, "The '$alias' command is not registered in local.");
        }
    }
}
