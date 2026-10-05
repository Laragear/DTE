<?php

namespace Laragear\Dte\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Builders\Iecv\IecvPropertyData;
use Laragear\Dte\Builders\Iecv\IecvPurchaseData;
use Laragear\Dte\Configuration\ConfigurationManager;
use Laragear\Dte\Enums\IecvStatus;
use Laragear\Dte\Enums\IecvType;
use Laragear\Dte\Events\IecvSent;
use Laragear\Dte\Gateways\IecvUploadGateway;
use Laragear\Dte\Jobs\PollIecvTrackIdJob;
use Laragear\Dte\Models\SiiDte;
use Laragear\Dte\Models\SiiIecv;
use Laragear\Rut\Rut;
use LogicException;
use Throwable;

use function sprintf;

class IecvService
{
    /**
     * Create a new IECV Service instance.
     */
    public function __construct(
        protected IecvGenerator $generator,
        protected IecvUploadGateway $upload,
        protected ConfigurationManager $configuration,
        protected ConfigRepository $config,
        protected Dispatcher $event,
        protected DateFactory $date,
    ) {
        //
    }

    /**
     * Resolve the fiscal resolution backing a book from the issuer.
     *
     * @return array{0: string, 1: int}
     */
    protected function resolutionFor(Rut $issuer, ?string $date, ?int $number): array
    {
        if ($date !== null && $number !== null) {
            return [$date, $number];
        }

        // Books carry the same <FchResol> and <NroResol> caratula values as the
        // document envelopes, so they are read from the same issuer registration
        // rather than from a separate setting. Callers that already know the
        // resolution may pass it explicitly; otherwise the issuer is consulted.
        $issuerData = $this->configuration->getIssuer($issuer);

        return [
            $date ?? $issuerData->resolutionDate,
            $number ?? $issuerData->resolutionNumber,
        ];
    }

    /**
     * Build, sign, and upload a Sales Book to the SII.
     *
     * @param  Collection<int, SiiDte>  $dtes
     * @param  array<int, IecvPropertyData>  $properties
     */
    public function sendSales(
        Rut $issuer,
        Collection $dtes,
        string $period,
        ?string $resolutionDate = null,
        ?int $resolutionNumber = null,
        ?Rut $senderRut = null,
        array $properties = [],
    ): SiiIecv {
        $this->assertDocumentsNotBooked($dtes);
        $this->assertPeriodNotBooked($issuer, IecvType::Sales, $period);

        [$resolutionDate, $resolutionNumber] = $this->resolutionFor($issuer, $resolutionDate, $resolutionNumber);

        $senderRut ??= $this->configuration->getSender($issuer);

        $book = $this->persist($issuer, $senderRut, IecvType::Sales, $period, $resolutionDate, $resolutionNumber);
        $book->update(['status' => IecvStatus::Building]);

        $xml = $this->generator->generateSales(
            $issuer, $dtes, $period, $resolutionDate, $resolutionNumber, $senderRut, $properties
        );

        return $this->dispatch($book, $xml, $dtes);
    }

    /**
     * Build, sign, and upload a Purchases Book to the SII.
     *
     * @param  array<int, IecvPurchaseData>  $entries
     * @param  array<int, IecvPropertyData>  $properties
     */
    public function sendPurchases(
        Rut $issuer,
        array $entries,
        string $period,
        ?string $resolutionDate = null,
        ?int $resolutionNumber = null,
        ?Rut $senderRut = null,
        array $properties = [],
    ): SiiIecv {
        $this->assertPurchasesPeriodNotBooked($issuer, $period);

        [$resolutionDate, $resolutionNumber] = $this->resolutionFor($issuer, $resolutionDate, $resolutionNumber);

        $senderRut ??= $this->configuration->getSender($issuer);

        $book = $this->persist($issuer, $senderRut, IecvType::Purchases, $period, $resolutionDate, $resolutionNumber);
        $book->update(['status' => IecvStatus::Building]);

        $xml = $this->generator->generatePurchases(
            $issuer, $entries, $period, $resolutionDate, $resolutionNumber, $senderRut, $properties
        );

        // Purchase entries carry their own RUTs, so the documents to link are
        // resolved from the entries rather than from a collection of models.
        return $this->dispatch($book, $xml, $this->documentsFor($entries));
    }

    /**
     * Persist the book record before any external work begins.
     */
    protected function persist(
        Rut $issuer,
        Rut $sender,
        IecvType $type,
        string $period,
        string $resolutionDate,
        int $resolutionNumber,
    ): SiiIecv {
        return SiiIecv::create([
            'issuer_rut' => $issuer,
            'sender_rut' => $sender,
            'type' => $type,
            'period' => $period,
            'resolution_date' => $resolutionDate,
            'resolution_number' => $resolutionNumber,
            'status' => IecvStatus::Building,
        ]);
    }

    /**
     * Upload the signed XML and schedule the status poll.
     *
     * @param  iterable<int, SiiDte>  $dtes
     */
    protected function dispatch(SiiIecv $book, string $xml, iterable $dtes): SiiIecv
    {
        $book->update(['xml' => $xml, 'status' => IecvStatus::Signing]);
        $book->update(['status' => IecvStatus::Sending]);

        try {
            $trackId = $this->upload->upload(
                $book->issuer_rut, $book->sender_rut, $xml
            );
        } catch (Throwable $e) {
            $book->update(['status' => IecvStatus::Failed]);

            throw $e;
        }

        $now = $this->date->now();

        $book->update([
            'track_id' => $trackId,
            'status' => IecvStatus::Uploaded,
            'poll_at' => $now,
            'uploaded_at' => $now,
        ]);

        $this->attachDocuments($book, $dtes);

        PollIecvTrackIdJob::dispatch($book)
            ->onConnection($this->config->get('dte.queue.track.connection'))
            ->onQueue($this->config->get('dte.queue.track.name', 'default'))
            ->delay($now);

        $this->event->dispatch(new IecvSent($book));

        return $book;
    }

    /**
     * Link the documents included in the book.
     *
     * @param  iterable<int, SiiDte>  $dtes
     */
    protected function attachDocuments(SiiIecv $book, iterable $dtes): void
    {
        $ids = [];

        foreach ($dtes as $dte) {
            $ids[] = $dte->getKey();
        }

        if ($ids !== []) {
            SiiDte::query()->whereKey($ids)->update(['sii_iecv_id' => $book->getKey()]);
        }
    }

    /**
     * Resolve the persisted documents referenced by purchase entries.
     *
     * @param  array<int, IecvPurchaseData>  $entries
     * @return array<int, SiiDte>
     */
    protected function documentsFor(array $entries): array
    {
        $documents = SiiDte::query()->get();

        // Matched on issuer RUT and issued date: a purchase entry carries no local
        // DTE key, and folios only repeat across issuers and periods, so the date
        // is required to disambiguate.
        $matched = [];

        foreach ($entries as $entry) {
            $rut = $entry->issuerRut instanceof Rut ? $entry->issuerRut : Rut::parse($entry->issuerRut);

            foreach ($documents as $document) {
                if (! $document->issuer_rut->isEqual($rut)) {
                    continue;
                }

                if ($document->issued_on->format('Y-m-d') === $entry->issuedOn) {
                    $matched[$document->getKey()] = $document;
                }
            }
        }

        return array_values($matched);
    }

    /**
     * Ensure no document was already filed in a book the SII has ruled on.
     *
     * @param  Collection<int, SiiDte>  $dtes
     */
    protected function assertDocumentsNotBooked(Collection $dtes): void
    {
        foreach ($dtes as $dte) {
            $book = $dte->relationLoaded('iecv') ? $dte->getRelation('iecv') : $dte->iecv()->first();

            if ($book === null || $book->status->isNotTerminalState()) {
                continue;
            }

            throw new LogicException(sprintf(
                'The DTE [%s] folio [%s] was already sent to the SII in book [%s] (%s %s) and cannot be filed again.',
                $dte->getKey(),
                $dte->folio,
                $book->getKey(),
                $book->type->value,
                $book->period,
            ));
        }
    }

    /**
     * Ensure the period was not already filed for this issuer and operation.
     */
    protected function assertPeriodNotBooked(Rut $issuer, IecvType $type, string $period): void
    {
        $existing = SiiIecv::query()
            ->where('issuer_num', $issuer->num)
            ->where('type', $type)
            ->where('period', $period)
            ->whereIn('status', [
                IecvStatus::Pending,
                IecvStatus::Building,
                IecvStatus::Signing,
                IecvStatus::Sending,
                IecvStatus::Uploaded,
            ])
            ->first();

        if ($existing === null) {
            return;
        }

        throw new LogicException(sprintf(
            'The period [%s] for issuer [%s] is already filed in book [%s] (%s) and is awaiting the SII verdict.',
            $period,
            $issuer->formatBasic(),
            $existing->getKey(),
            $existing->status->value,
        ));
    }

    /**
     * Ensure the purchases period was not already filed for this issuer.
     */
    protected function assertPurchasesPeriodNotBooked(Rut $issuer, string $period): void
    {
        $this->assertPeriodNotBooked($issuer, IecvType::Purchases, $period);
    }
}
