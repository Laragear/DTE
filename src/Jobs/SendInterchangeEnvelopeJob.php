<?php

namespace Laragear\Dte\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laragear\Dte\Actions\CreateEnvelope\CreateEnvelope;
use Laragear\Dte\Enums\DteType;
use Laragear\Dte\Mail\Interchange\InterchangeEnvelopeMail;
use Laragear\Dte\Mailbox\RutEmailResolver;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Rut\Rut;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

#[Backoff([60, 120, 300])]
#[Tries(3)]
#[Timeout(120)]
class SendInterchangeEnvelopeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public SiiDteEnvelope $envelope,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(
        LoggerInterface $logger,
        RutEmailResolver $resolver,
        CreateEnvelope $creator,
        MailFactory $mailFactory,
        ConfigRepository $config,
    ): void {
        foreach ($this->getUniqueReceivers() as $dte) {
            $this->sendToReceiver(
                $dte->receiver_rut,
                $logger,
                $resolver,
                $creator,
                $mailFactory,
                $config
            );
        }
    }

    /**
     * Retrieve a distinct collection of receiver RUTs within the envelope.
     */
    protected function getUniqueReceivers(): Collection
    {
        // Ignores Receipts and Exempt Receipts, as they are not sent through
        // B2B interchange.
        return $this->envelope->dtes()
            ->whereNotIn('document_type', [DteType::Receipt->value, DteType::ExemptReceipt->value])
            ->select('receiver_num', 'receiver_vd')
            ->distinct()
            ->get();
    }

    /**
     * Build and dispatch the interchange envelope for a specific receiver.
     */
    protected function sendToReceiver(
        Rut $receiverRut,
        LoggerInterface $logger,
        RutEmailResolver $resolver,
        CreateEnvelope $creator,
        MailFactory $mailFactory,
        ConfigRepository $config,
    ): void {
        $email = $resolver->resolve($receiverRut);

        if ($email === null) {
            $logger->warning("Skipped interchange envelope dispatch for {$receiverRut->formatBasic()}: No DIM email resolved.");

            return;
        }

        try {
            $xml = $this->buildEnvelopeXml($creator, $receiverRut);
            $this->dispatchEmail($xml, $receiverRut, $email, $mailFactory, $config);
        } catch (Throwable $e) {
            $logger->error("Failed to assemble and send interchange to {$email}: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate the XML string for the ephemeral receiver envelope.
     */
    protected function buildEnvelopeXml(CreateEnvelope $creator, Rut $receiverRut): string
    {
        return $creator->forSharing($this->envelope, $receiverRut)->payload->xml
            ?: throw new RuntimeException('Failed to cast ephemeral envelope to XML string.');
    }

    /**
     * Dispatch the interchange email to the receiver.
     */
    protected function dispatchEmail(
        string $xml,
        Rut $receiverRut,
        string $email,
        MailFactory $mailFactory,
        ConfigRepository $config,
    ): void {
        $issuer = $this->envelope->issuer_rut->formatBasic();
        $subject = "Envío DTE - {$issuer} a {$receiverRut->formatBasic()}";

        $mailerName = $config->get('dte.dim.mailer');
        $mailer = $mailFactory->mailer($mailerName);

        $mailer->to($email)->send(
            new InterchangeEnvelopeMail($xml)->subject($subject)
        );
    }
}
