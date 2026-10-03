<?php

namespace Laragear\Dte\Builders;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\DateFactory;
use InvalidArgumentException;
use Laragear\Dte\Enums\AecStatus;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Events\AecCessionCreated;
use Laragear\Dte\Events\AecCessionCreating;
use Laragear\Dte\Models\SiiAecCession;
use Laragear\Dte\Models\SiiDte;
use Laragear\Rut\Rut;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

class AecCessionBuilder
{
    protected SiiDte $dte;

    protected Rut $assigneeRut;

    protected string $assigneeName;

    protected string $assigneeAddress;

    protected string $assigneeEmail;

    protected Rut $authorizedSigner;

    protected string $authorizedName;

    protected string $cedentEmail;

    protected ?int $amount = null;

    protected DateTimeImmutable $dueDate;

    protected ?string $terms = null;

    /**
     * Create a new Builder instance.
     */
    public function __construct(
        protected Dispatcher $events,
        protected DateFactory $date,
        protected LoggerInterface $log,
    ) {
        $this->dueDate = $this->date->now('America/Santiago')->startOfDay()->toDateTimeImmutable();
    }

    /**
     * Set the DTE to cede.
     */
    public function forDte(SiiDte $dte): static
    {
        $this->dte = $dte;

        return $this;
    }

    /**
     * Set the assignee (Factoring company) details.
     */
    public function to(Rut|string $rut, string $name): static
    {
        $this->assigneeRut = Rut::parse($rut);
        $this->assigneeName = $name;

        return $this;
    }

    /**
     * Set the assignee address and email.
     */
    public function address(string $address, string $email): static
    {
        $this->assigneeAddress = $address;
        $this->assigneeEmail = $email;

        return $this;
    }

    /**
     * Set the authorized signer (person authorizing the cession) and the cedent email.
     */
    public function authorizedBy(Rut|string $rut, string $name, string $email): static
    {
        $this->authorizedSigner = Rut::parse($rut);
        $this->authorizedName = $name;
        $this->cedentEmail = $email;

        return $this;
    }

    /**
     * Set the amount to cede. Defaults to the total amount of the document.
     */
    public function amount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Set the last due date of the cession.
     */
    public function dueDate(DateTimeImmutable|string $date): static
    {
        if (is_string($date)) {
            $date = $this->date->parse($date, 'America/Santiago')->toDateTimeImmutable();
        }

        $this->dueDate = $date;

        return $this;
    }

    /**
     * Set additional terms for the cession.
     */
    public function terms(string $terms): static
    {
        $this->terms = $terms;

        return $this;
    }

    /**
     * Persist the pending cession and its payload into the database.
     */
    public function build(): SiiAecCession
    {
        $this->validate();

        $this->events->dispatch(new AecCessionCreating($this));

        // Single-row write: the unique (sii_dte_id, cession_number) key resolves
        // sequence races, so no transaction is required. Logs the conflict.
        try {
            $cession = $this->persist();
        } catch (Throwable $e) {
            $this->log->warning('AEC cession persistence failed.', [
                'flow' => 'aec-cession',
                'dte_id' => $this->dte->getKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->events->dispatch(new AecCessionCreated($cession));

        return $cession;
    }

    /**
     * Validate the builder state before persisting.
     */
    protected function validate(): void
    {
        if (!isset($this->dte)) {
            throw new LogicException('A document must be set to create a cession.');
        }

        if (!isset($this->assigneeRut, $this->assigneeName, $this->assigneeAddress, $this->assigneeEmail)) {
            throw new LogicException('The assignee details (to, address) must be set.');
        }

        if (!isset($this->authorizedSigner, $this->authorizedName, $this->cedentEmail)) {
            throw new LogicException('The authorized signer details (authorizedBy) must be set.');
        }

        if (!in_array($this->dte->document_type, DteType::AEC_TRANSFERABLE, true)) {
            throw new InvalidArgumentException('The DTE type cannot be transferred through an AEC.');
        }

        if ($this->amount > $this->dte->amount_total) {
            throw new InvalidArgumentException('The cession amount cannot exceed the DTE total amount.');
        }
    }

    /**
     * Persist the cession model.
     */
    protected function persist(): SiiAecCession
    {
        $cessionNumber = $this->dte->aecCessions()->max('cession_number') ?? 0;

        return $this->dte->aecCessions()->create([
            'cession_number' => $cessionNumber + 1,
            'rut' => $this->assigneeRut,
            'amount_total' => $this->amount ?? $this->dte->amount_total,
            'last_due_on' => $this->dueDate,
            'terms' => $this->terms,
            'data' => $this->payloadData(),
            'status' => AecStatus::Pending,
        ]);
    }

    /**
     * Return the JSON-safe raw builder input.
     *
     * @return array<string, mixed>
     */
    protected function payloadData(): array
    {
        return [
            'assignee_name' => $this->assigneeName,
            'assignee_address' => $this->assigneeAddress,
            'assignee_email' => $this->assigneeEmail,
            'authorized_signer_rut' => $this->authorizedSigner->formatRaw(),
            'authorized_signer_name' => $this->authorizedName,
            'cedent_email' => $this->cedentEmail,
        ];
    }
}
