<?php

namespace Laragear\Dte\Environment;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Laragear\Dte\Enums\DteEnvironment as DteEnv;

class EnvironmentResolver
{
    /**
     * The resolved DTE environment.
     */
    protected DteEnv $resolved;

    /**
     * Create an EnvironmentResolver instance.
     */
    public function __construct(
        protected Repository $config,
        protected Application $app,
    ) {
        //
    }

    /**
     * Resolve and validate the configured DTE environment.
     */
    public function resolve(): DteEnv
    {
        return $this->resolved ??= $this->getCurrentEnvironment();
    }

    /**
     * Dynamically override the environment.
     *
     * @return $this
     */
    public function setEnvironment(DteEnv|string|null $environment): static
    {
        $this->config->set('dte.environment', $environment instanceof DteEnv ? $environment->value : $environment);
        $this->flush();

        return $this;
    }

    /**
     * Resolves the current environment.
     */
    protected function getCurrentEnvironment(): DteEnv
    {
        $raw = $this->config->get('dte.environment') ?? $this->app->environment();

        return match ($raw) {
            'production', DteEnv::Production => DteEnv::Production,
            'testing', DteEnv::Testing => DteEnv::Testing,
            'certification', DteEnv::Certification => DteEnv::Certification,
            default => DteEnv::Local,
        };
    }

    /**
     * Flush the memoized resolved environment.
     *
     * @return $this
     */
    public function flush(): static
    {
        unset($this->resolved);

        return $this;
    }
}
