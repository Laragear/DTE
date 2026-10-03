<?php

namespace Laragear\Dte\Actions;

use Illuminate\Contracts\Config\Repository;
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
     * @return array<string, int>
     */
    public function handle(mixed $source, RcvType|string $type, Rut|string|null $issuer = null): array
    {
        // File path, SplFileInfo, UploadedFile, string payload, or stream resource.
        if (is_string($type)) {
            $type = RcvType::from($type);
        }

        try {
            $issuer ??= $this->configManager->getIssuer()->rut;
        } catch (Throwable $e) {
            throw new RuntimeException('There is no issuer to be resolved and sync RCV.', previous: $e);
        }

        return $this->cuadratura->forParsing(
            $this->parser->forBatch($source, $type, Rut::parse($issuer))
        );
    }
}
