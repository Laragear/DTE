<?php

namespace Laragear\Dte\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Builders\Iecv\IecvPurchaseData;
use Laragear\Dte\Enums\DteStatus;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Services\IecvService;
use Laragear\Rut\Rut;

use function is_numeric;
use function mb_strtolower;
use function str_contains;

class SendIecvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dte:send-iecv
                            {--period= : Tax period in AAAA-MM format (defaults to last month)}
                            {--type=sales : Book operation, "sales" or "purchases"}
                            {--issuer= : RUT of the company filing the book}
                            {--sender= : RUT of the user sending the book (defaults to issuer)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assemble an IECV book from a tax period, sign it, and send it to the SII';

    /**
     * Execute the console command.
     */
    public function handle(IecvService $service, DateFactory $date): int
    {
        $period = $this->option('period');
        $period = is_string($period) && $period !== ''
            ? $period
            : $date->now()->subMonth()->format('Y-m');

        $issuer = $this->rut('issuer') ?? SiiDte::query()->latest('id')->first()?->issuer_rut;

        if ($issuer === null) {
            $this->error('No issuer RUT could be resolved. Pass --issuer or store at least one DTE.');

            return self::FAILURE;
        }

        $sender = $this->rut('sender') ?? $issuer;
        $type = $this->bookType();
        $dtes = $this->documents($period);

        if ($dtes->isEmpty()) {
            $this->error("No documents found to file for period [{$period}].");

            return self::FAILURE;
        }

        $book = $type === IecvType::Sales
            ? $service->sendSales($issuer, $dtes, $period, senderRut: $sender)
            : $service->sendPurchases(
                $issuer, $this->purchaseEntries($dtes), $period, senderRut: $sender
            );

        $this->info(
            "Book [{$book->getKey()}] ({$book->type->value} {$book->period}) sent with Track ID: {$book->track_id}"
        );

        return self::SUCCESS;
    }

    /**
     * Resolve the book operation to file.
     */
    protected function bookType(): IecvType
    {
        $type = $this->option('type');

        return match (mb_strtolower(is_string($type) ? $type : '')) {
            'purchases',
            'purchase',
            'compra' => IecvType::Purchases,
            default => IecvType::Sales,
        };
    }

    /**
     * Parse an explicitly provided RUT option.
     */
    protected function rut(string $option): ?Rut
    {
        $value = $this->option($option);

        if (is_string($value) && (is_numeric($value) || str_contains($value, '-'))) {
            return Rut::parse($value);
        }

        return null;
    }

    /**
     * Retrieve the documents of the period awaiting a book.
     *
     * @return Collection<int, SiiDte>
     */
    protected function documents(string $period): Collection
    {
        return SiiDte::query()
            ->whereNotNull('folio')
            ->whereIn('status', [DteStatus::Accepted, DteStatus::Sent])
            ->whereBetween('issued_on', ["{$period}-01", "{$period}-31"])
            ->oldest('issued_on')
            ->get();
    }

    /**
     * Map the documents into purchase entries for a purchases book.
     *
     * @param  Collection<int, SiiDte>  $dtes
     * @return array<int, IecvPurchaseData>
     */
    protected function purchaseEntries(Collection $dtes): array
    {
        return $dtes->map(static fn (SiiDte $dte): IecvPurchaseData => new IecvPurchaseData(
            $dte->document_type,
            // Only documents with a folio and issue date are collected, so both
            // are always set here.
            (int) $dte->folio,
            $dte->issued_on?->format('Y-m-d') ?? '',
            $dte->issuer_rut,
            $dte->amount_net,
            $dte->amount_exempt,
            $dte->iva_common_use,
        ))->all();
    }
}
