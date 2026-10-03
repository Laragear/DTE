<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\DateFactory as Date;
use Illuminate\Support\Str;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Console\Commands\Concerns\HasDefaultRut;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Models\SiiCaf;
use Laragear\Dte\Proxies\OpenSslProxy as OpenSsl;
use Laragear\Rut\Rut;
use RuntimeException;
use Throwable;
use const OPENSSL_KEYTYPE_RSA;

class MakeFakeCafCommand extends Command
{
    use HasDefaultRut;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:make-fake-caf
                            {--rut= : The RUT for the CAF (e.g., 76.123.456-0)}
                            {--type=33 : The DTE type (e.g., 33 for Invoice, 39 for Receipt, etc.)}
                            {--from=1 : Starting folio number}
                            {--to=100 : Ending folio number}
                            {--file= : The path to save the XML CAF file}
                            {--db : Insert the CAF directly into the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a dummy SII CAF XML file containing valid structural tags and a newly minted RSA keypair.';

    /**
     * Execute the console command.
     */
    public function handle(Filesystem $file, Date $date, OpenSsl $openSsl, ConfigurationManager $manager): int
    {
        $rut = $this->rut($manager);
        $type = $this->getDteType();

        $from = (int) $this->option('from');
        $to = (int) $this->option('to');

        $this->info("Generating dummy CAF for RUT {$rut} (Type: {$type->value}, Folios: {$from}-{$to})...");

        $xml = $this->generateXml($date, $rut, $type, $from, $to, $openSsl, $file);

        if ($this->option('db')) {
            $this->createCafInDatabase($date, $rut, $type, $from, $to, $xml);
        }

        if ($this->option('file')) {
            $this->createCafInFilesystem($file, $this->option('file'), $xml);
        }

        if (!$this->option('db') && !$this->option('file')) {
            $this->line($xml);
        }

        return self::SUCCESS;
    }

    /**
     * Retrieves the correct DTE Type or fails.
     */
    protected function getDteType(): DteType
    {
        $option = $this->option('type');

        if ($option instanceof DteType) {
            return $option;
        }

        return DteType::tryFrom((int) $option)
            ?? $this->fail("Invalid DTE type provided: {$this->option('type')}");
    }

    /**
     * Generate the XML with some minted signatures and hashes.
     */
    protected function generateXml(
        Date $date,
        Rut $rut,
        DteType $type,
        int $from,
        int $to,
        OpenSsl $openSsl,
        Filesystem $file
    ): string {
        $key = $openSsl->pkeyNew([
            'private_key_bits' => 1024, // CAF uses 1024 bits usually
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]) ?: $this->fail('Failed to generate RSA key pair.');

        $details = $openSsl->pkeyGetDetails($key);

        $privateKey = '';
        $openSsl->pkeyExport($key, $privateKey);

        return Str::replace(
            ['{rut}', '{type}', '{from}', '{to}', '{date}', '{modulus}', '{exponent}', '{privateKey}', '{publicKey}'],
            [
                $rut->formatBasic(),
                $type->value,
                $from,
                $to,
                $date->now('America/Santiago')->toDateString(),
                Str::toBase64($details['rsa']['n']),
                Str::toBase64($details['rsa']['e']),
                $privateKey,
                $details['key'],
            ],
            $file->get(__DIR__.'/stubs/FakeCaf.xml')
        );
    }

    /**
     * Creates the CAF in the database.
     */
    protected function createCafInDatabase(Date $date, Rut $rut, DteType $type, int $from, int $to, string $xml): void
    {
        SiiCaf::create([
            'rut' => clone $rut,
            'document_type' => $type,
            'folio_from' => $from,
            'folio_to' => $to,
            'folio_current' => $from,
            'authorized_on' => $date->now(),
            'expires_on' => $date->now()->addMonths(6),
            'xml' => $xml,
        ]);

        $this->info('Successfully inserted fake CAF into the database.');
    }

    /**
     * Create the CAF as a file into the local filesystem.
     */
    protected function createCafInFilesystem(Filesystem $file, string $path, string $xml): void
    {
        try {
            $file->put($path, $xml);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to write CAF to $path.", previous: $e);
        }

        $this->info("Successfully created fake CAF at: {$path}");
    }
}
