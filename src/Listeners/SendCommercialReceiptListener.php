<?php

namespace Laragear\Dte\Listeners;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Laragear\Dte\Events\InboundDteAcknowledged;
use Laragear\Dte\Mail\Interchange\RespuestaDteMail;
use function blank;

class SendCommercialReceiptListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create a new listener instance.
     */
    public function __construct(
        protected MailFactory $mailFactory,
        protected ConfigRepository $config
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InboundDteAcknowledged $event): void
    {
        $interchangeLog = $event->document->interchangeLog;

        if (!$interchangeLog || blank($interchangeLog->sender)) {
            return;
        }

        $mailerName = $this->config->get('dte.dim.mailer');

        $this->mailFactory->mailer($mailerName)->to($interchangeLog->sender)->send(
            new RespuestaDteMail($event->receiptXml)
        );
    }
}
