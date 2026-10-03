<?php

namespace Laragear\Dte;

use Illuminate\Contracts\Cache\Repository as CacheRepositoryContract;
use Illuminate\Contracts\Config\Repository as ConfigRepositoryContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\ServiceProvider;
use Laragear\Dte\Certificate\CertificateResolver;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Console\Commands\CheckNearDepletedCafsCommand;
use Laragear\Dte\Console\Commands\CompileDteXmlCommand;
use Laragear\Dte\Console\Commands\FetchInboundMailboxCommand;
use Laragear\Dte\Console\Commands\MakeFakeCafCommand;
use Laragear\Dte\Console\Commands\MakeFakeCertificateCommand;
use Laragear\Dte\Console\Commands\PackManualDtesCommand;
use Laragear\Dte\Console\Commands\PackReadyDtesCommand;
use Laragear\Dte\Console\Commands\PollTrackStatusCommand;
use Laragear\Dte\Console\Commands\ProcessAndSendEnvelopeCommand;
use Laragear\Dte\Console\Commands\PurgeDatabaseCommand;
use Laragear\Dte\Console\Commands\RejectExpiringPhantomInvoicesCommand;
use Laragear\Dte\Contracts\CertificateResolverInterface;
use Laragear\Dte\Contracts\TokenProviderInterface;
use Laragear\Dte\Environment\EnvironmentResolver;
use Laragear\Dte\Events\InboundDteAcknowledged;
use Laragear\Dte\Listeners\SendCommercialReceiptListener;
use Laragear\Dte\Mailbox\MailboxManager;
use Laragear\Dte\Pdf\Ted\Pdf417Encoder;
use Laragear\Dte\Pdf\Ted\Pdf417Lookup;
use Laragear\Dte\Pdf\Ted\Pdf417Renderer;
use Laragear\Dte\Proxies\GDProxy;
use Laragear\Dte\Support\TokenAuthenticator;
use Laragear\Dte\Support\TokenRepository;
use Laragear\Dte\Validation\ValidatesSiiDocuments;

class DteServiceProvider extends ServiceProvider
{
    public const string CONFIG = __DIR__.'/../config/dte.php';

    public const string MIGRATIONS = __DIR__.'/../database/migrations';

    public const string VIEWS = __DIR__.'/../resources/views';

    public const string LANG = __DIR__.'/../lang';

    public const string XSD_ASSETS = __DIR__.'/../resources/xsd';

    /**
     * Rules to register into the validator.
     *
     * @var array<string[]>
     */
    public const array RULES = [
        ['sii_certificate', 'validateSiiCertificate', 'dte::validation.certificate'],
        ['sii_caf', 'validateSiiCaf', 'dte::validation.caf'],
        ['sii_rut', 'validateSiiRut', 'dte::validation.rut'],
    ];

    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(static::CONFIG, 'dte');
        $this->loadViewsFrom(static::VIEWS, 'dte');
        $this->loadTranslationsFrom(static::LANG, 'dte');

        $this->app->scoped(EnvironmentResolver::class);
        $this->app->singleton(ConfigurationManager::class);
        $this->app->singleton(CertificateResolver::class);
        $this->app->bind(CertificateResolverInterface::class, CertificateResolver::class);

        // TokenRepository: the only owner of the token cache mechanics.
        $this->app->scoped(TokenRepository::class);
        $this->app
            ->when(TokenRepository::class)
            ->needs(CacheRepositoryContract::class)
            ->give(function (Container $app) {
                return $app->make('cache')->store($app->make('config')->get('dte.cache.store'));
            });

        // TokenAuthenticator: owns SII auth + refresh, the only TokenRepository caller.
        $this->app->scoped(TokenAuthenticator::class);

        // Bind the token interface to the authenticator (gateways no longer authenticate).
        $this->app->bind(TokenProviderInterface::class, TokenAuthenticator::class);

        $this->app->scoped(MailboxManager::class);

        $this->app->bind(Pdf417Encoder::class, static function (Container $app): Pdf417Encoder {
            $encoder = new Pdf417Encoder(
                $app->make(Pdf417Lookup::class),
                new Pdf417Renderer(
                    $app->make(GDProxy::class),
                    [
                        'format' => 'png',
                        'scale' => 3,
                        'ratio' => 3,
                        'padding' => 20,
                    ],
                ),
            );
            $encoder->setSecurityLevel(5);
            $encoder->setColumns(9);

            return $encoder;
        });

        // Use the default certificate resolver that uses development values
        CertificateResolver::resolveUsingDefaults();
    }

    /**
     * Boot the service provider.
     */
    public function boot(ConfigRepositoryContract $config, Dispatcher $event, EnvironmentResolver $environment): void
    {
        $this->callAfterResolving('validator', static function (Factory $validator, Container $app): void {
            $translator = $app->make('translator');

            foreach (static::RULES as [$rule, $extension, $key]) {
                $validator->extend(
                    $rule,
                    ValidatesSiiDocuments::$extension(...),
                    $translator->get($key),
                );
            }
        });

        $this->publishes([static::CONFIG => $this->app->configPath('dte.php')], 'config');
        $this->publishes([static::XSD_ASSETS => $this->app->resourcePath('xsd')], 'xsd');

        $this->publishesMigrations([
            static::MIGRATIONS => $this->app->databasePath('/migrations'),
        ], 'migrations');

        // Allow all these commands to be fireable inside the application programmatically.
        // For example, the user can call one of these Artisan commands from the frontend
        // or run them through a webhook. This avoids binding these exclusively to CLI.
        $this->commands([
            CheckNearDepletedCafsCommand::class,
            FetchInboundMailboxCommand::class,
            RejectExpiringPhantomInvoicesCommand::class,
            PollTrackStatusCommand::class,
            CompileDteXmlCommand::class,
            ProcessAndSendEnvelopeCommand::class,
            PackManualDtesCommand::class,
            PackReadyDtesCommand::class,
            PurgeDatabaseCommand::class,
        ]);

        if ($config->get('dte.dim.auto_email_receipts', false)) {
            $event->listen(
                InboundDteAcknowledged::class,
                SendCommercialReceiptListener::class,
            );
        }

        /** Validate the operational environment before serving the library. */
        if ($this->shouldRegisterFakeCommands($environment)) {
            $this->commands([
                MakeFakeCertificateCommand::class,
                MakeFakeCafCommand::class,
            ]);
        }
    }

    /**
     * Check if the environment allows registering fake certificate/CAF commands.
     */
    protected function shouldRegisterFakeCommands(EnvironmentResolver $environment): bool
    {
        return $this->app->runningInConsole() && $environment->resolve()->allowsFakeAssets();
    }
}
