<?php

namespace Laragear\Dte\Actions\CreateEnvelope\Pipes;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Laragear\Dte\Actions\CreateEnvelope\Assembly;
use Laragear\Dte\Enums\EnvelopeStatus;
use RuntimeException;

class PersistEnvelopePayload
{
    /**
     * Create a new Persist Envelope Payload instance.
     */
    public function __construct(protected Filesystem $file)
    {
        //
    }

    /**
     * Hande the incoming DTE Envelope Assembly.
     *
     * @param  Closure(Assembly): Assembly  $next
     */
    public function handle(Assembly $assembly, Closure $next): Assembly
    {
        if ($assembly->ephemeral) {
            return $next($assembly);
        }

        $xml = $assembly->requireDocument()->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Unable to serialize the signed envelope XML.');
        }

        $payload = $assembly->envelope->payload()->updateOrCreate([], ['xml' => $xml]);

        $assembly->envelope->setRelation('payload', $payload);
        $assembly->envelope->transitionTo(EnvelopeStatus::Signed);

        $assembly->document = null;
        $assembly->path = null;

        return $next($assembly);
    }
}
