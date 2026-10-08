<?php

namespace Laragear\Dte\Actions;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Laragear\Dte\Actions\Cuadratura\Sync;
use Laragear\Dte\Actions\RcvParsing\Parse;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Enums\RcvType;
use Laragear\Rut\Rut;
use RuntimeException;
use Throwable;

use function is_string;

class SyncRcv
{
    /**
     * Create a new SyncRcv action instance.
     */
    public function __construct(
        protected Repository $config,
        protected Parse $parser,
        protected Sync $cuadratura,
        protected ConfigurationManager $configManager,
    ) {
        //
    }

    /**
     * Executes the RCV synchronization pipeline against a source file or payload.
     *
     * @param  ?string  $period  Must be `YYYY-MM` format.
     * @return array<string, int>
     */
    public function handle(mixed $source, RcvType|string $type, Rut|string|null $issuer = null, ?string $period = null): array
    {
        // File path, SplFileInfo, UploadedFile, string payload, or stream resource.
        if (is_string($type)) {
            $type = RcvType::from($type);
        }

        if ($period !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            // Throw when the period is not a valid YYYY-MM month.
            throw new InvalidArgumentException("The period [{$period}] is not a valid YYYY-MM month.");
        }

        try {
            $issuer ??= $this->configManager->getIssuer()->rut;
        } catch (Throwable $e) {
            throw new RuntimeException('There is no issuer to be resolved and sync RCV.', previous: $e);
        }

        return $this->cuadratura->forParsing(
            $this->parser->forBatch($source, $type, Rut::parse($issuer), $period)
        );
    }
}
