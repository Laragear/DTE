<?php

namespace Laragear\Dte\Actions\InboundDte\Pipes;

use Closure;
use Illuminate\Support\DateFactory;
use Laragear\Dte\Actions\InboundDte\InboundDteData;
use Laragear\Dte\Models\SiiInterchangeLog;

class CreateInterchangeLog
{
    /**
     * Create a new Create Interchange Log instance.
     */
    public function __construct(
        protected DateFactory $date,
    ) {
        //
    }

    /**
     * Handle the incoming Inbound DTE.
     *
     * @param  Closure(InboundDteData):InboundDteData  $next
     */
    public function handle(InboundDteData $data, Closure $next): InboundDteData
    {
        $data->log = SiiInterchangeLog::create([
            'message_id' => $data->email->messageId,
            'direction' => 'in',
            'type' => 'email',
            'sender' => $data->email->sender,
            'recipient' => 'resolved-from-xml',
            'subject' => $data->email->subject,
            'processed_at' => $this->date->now(),
        ]);

        return $next($data);
    }
}
