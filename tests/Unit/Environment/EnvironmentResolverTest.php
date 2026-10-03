<?php

namespace Tests\Unit\Environment;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Laragear\Dte\Enums\DteEnvironment;
use Laragear\Dte\Environment\EnvironmentResolver;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnvironmentResolverTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string, DteEnvironment}>
     */
    public static function providesAppEnvironments(): array
    {
        return [
            'local app' => ['local', null, DteEnvironment::Local],
            'development app' => ['development', null, DteEnvironment::Local],
            'staging app' => ['staging', null, DteEnvironment::Local],
            'sandbox app' => ['sandbox', null, DteEnvironment::Local],
            'custom app' => ['qa-custom', null, DteEnvironment::Local],
            'testing app' => ['testing', null, DteEnvironment::Testing],
            'production app' => ['production', null, DteEnvironment::Production],
        ];
    }

    /**
     * @return array<string, array{string, string, DteEnvironment}>
     */
    public static function providesExplicitOverrides(): array
    {
        return [
            'override local on production app' => ['production', 'local', DteEnvironment::Local],
            'override production on local app' => ['local', 'production', DteEnvironment::Production],
            'override certification on local app' => ['local', 'certification', DteEnvironment::Certification],
            'override testing on dev app' => ['development', 'testing', DteEnvironment::Testing],
            'override unknown string on production app falls back to local' => [
                'production', 'unknown-val', DteEnvironment::Local,
            ],
        ];
    }

    protected function resolver(string $appEnvironment, ?string $dteEnvironment): EnvironmentResolver
    {
        $app = Mockery::mock(Application::class);

        $app->allows('environment')->withNoArgs()->andReturn($appEnvironment);

        return new EnvironmentResolver(new Repository([
            'dte' => ['environment' => $dteEnvironment],
        ]), $app);
    }

    #[DataProvider('providesAppEnvironments')]
    public function test_normalizes_app_environments(
        string $appEnvironment,
        ?string $dteEnvironment,
        DteEnvironment $expected,
    ): void {
        $resolver = $this->resolver($appEnvironment, $dteEnvironment);

        static::assertSame($expected, $resolver->resolve());
    }

    #[DataProvider('providesExplicitOverrides')]
    public function test_resolves_explicit_config_override(
        string $appEnvironment,
        string $dteEnvironment,
        DteEnvironment $expected,
    ): void {
        $resolver = $this->resolver($appEnvironment, $dteEnvironment);

        static::assertSame($expected, $resolver->resolve());
    }

    public function test_set_environment_dynamically(): void
    {
        $resolver = $this->resolver('local', null);

        static::assertSame(DteEnvironment::Local, $resolver->resolve());

        $resolver->setEnvironment(DteEnvironment::Production);
        static::assertSame(DteEnvironment::Production, $resolver->resolve());

        $resolver->setEnvironment('testing');
        static::assertSame(DteEnvironment::Testing, $resolver->resolve());

        $resolver->setEnvironment(null);
        static::assertSame(DteEnvironment::Local, $resolver->resolve());
    }

    public function test_flush_resets_memoized_environment(): void
    {
        $app = Mockery::mock(Application::class);
        $app->allows('environment')->andReturn('local', 'production');

        $config = new Repository(['dte' => ['environment' => null]]);
        $resolver = new EnvironmentResolver($config, $app);

        static::assertSame(DteEnvironment::Local, $resolver->resolve());

        $resolver->flush();

        static::assertSame(DteEnvironment::Production, $resolver->resolve());
    }
}
