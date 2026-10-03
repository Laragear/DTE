<?php

namespace Laragear\Dte\Actions\InboundDte;

use Illuminate\Pipeline\Pipeline;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Models\SiiInterchangeLog;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @method InboundDteData thenReturn()
 */
class ProcessInboundDte extends Pipeline
{
    /**
     * The pipeline stages.
     *
     * @var class-string[]
     */
    protected $pipes = [
        Pipes\ValidateXmlStructure::class,
        Pipes\ParseInboundDocument::class,
        Pipes\CreateInterchangeLog::class,
        Pipes\ProcessEnvioDteDocuments::class,
        Pipes\ProcessRespuestaDteDocuments::class,
    ];

    /**
     * Process an inbound DTE email payload.
     *
     * @context Transaction
     */
    public function forEmail(InboundEmailData $email): InboundDteData
    {
        // Single transaction of the inbound flow: one email is one atomic unit, so a
        // replay never duplicates partially written documents. The message_id column
        // is unique, so overlapping mailbox runs skip duplicates.
        try {
            return SiiInterchangeLog::query()
                ->getConnection()
                ->transaction(fn() => $this->send(new InboundDteData($email))->through($this->pipes)->thenReturn());
        } catch (Throwable $e) {
            $this->getContainer()->make(LoggerInterface::class)->error(
                'Inbound DTE email processing failed, rolling back the email unit.', [
                    'flow' => 'inbound-email',
                    'message_id' => $email->messageId,
                    'sender' => $email->sender,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }
}
