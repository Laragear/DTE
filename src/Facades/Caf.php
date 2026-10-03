<?php

namespace Laragear\Dte\Facades;

use Illuminate\Support\Facades\Facade;
use Laragear\Dte\Caf\CafManager;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Rut\Rut;
use SplFileInfo;

/**
 * @method static SiiCaf store(string $xml)
 * @method static SiiCaf storeFile(string|SplFileInfo $file)
 * @method static SiiCaf annulFolios(Rut|string $issuer, DteType|int $documentType, string $reason, array $folios)
 *
 * @see CafManager
 */
class Caf extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return CafManager::class;
    }
}
