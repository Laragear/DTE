<?php

namespace Laragear\Dte\Actions\CreateEnvelope;

use Illuminate\Pipeline\Pipeline;
use Laragear\Dte\Enums\EnvelopeStatus;
use Laragear\Dte\Models\SiiDteEnvelope;
use Laragear\Rut\Rut;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @method Assembly thenReturn()
 */
class CreateEnvelope extends Pipeline
{
    /**
     * The envelope assembly stages.
     *
     * @var class-string[]
     */
    protected $pipes = [
        Pipes\InitializeEnvelope::class,
        Pipes\ValidateEnvelopeDocuments::class,
        Pipes\BuildCaratulaHeader::class,
        Pipes\EmbedDteNodes::class,
        Pipes\CanonicalizeEnvelope::class,
        Pipes\ApplyEnvelopeSignature::class,
        Pipes\PersistEnvelopePayload::class,
    ];

    /**
     * Assemble the envelope.
     */
    public function forEnvelope(SiiDteEnvelope $envelope): SiiDteEnvelope
    {
        // Single transaction of the envelope-build flow. The in-memory XML
        // build runs inside, but only the database writes roll back on failure
        // — the status is restored by the catch block either way.
        return $this->assemble($envelope, function (Assembly $assembly): SiiDteEnvelope {
            return $this->send($assembly)->thenReturn()->envelope;
        });
    }

    /**
     * Assemble an ephemeral envelope for interchange sharing.
     */
    public function forSharing(SiiDteEnvelope $envelope, Rut $receiverRut): SiiDteEnvelope
    {
        return $this->assemble($envelope, function (Assembly $assembly): SiiDteEnvelope {
            return $this->send($assembly)->thenReturn()->envelope;
        }, $receiverRut, true);
    }

    /**
     * Run the assembly inside the envelope-build transaction.
     */
    protected function assemble(
        SiiDteEnvelope $envelope,
        callable $build,
        ?Rut $receiverRut = null,
        bool $ephemeral = false,
    ): SiiDteEnvelope {
        // Claims a persisted, non-ephemeral envelope as Assembling with a
        // conditional UPDATE first: a duplicate run racing this one loses the
        // claim and aborts instead of building twice. Ephemeral sharing and
        // non-persisted envelopes skip the claim (they write no envelope rows.
        // The InitializeEnvelope pipe re-affirms the claimed status).
        $assembly = new Assembly($envelope, $receiverRut, ephemeral: $ephemeral);

        $currentStatus = $envelope->status;

        try {
            return $envelope->getConnection()->transaction(
                function () use ($envelope, $assembly, $build, $ephemeral): SiiDteEnvelope {
                    if (!$ephemeral && $envelope->exists) {
                        $this->claim($envelope);
                    }

                    return $build($assembly);
                }
            );
        } catch (Throwable $e) {
            $envelope->status = $currentStatus;
            $envelope->save();

            $this->getContainer()->make(LoggerInterface::class)->error(
                'Envelope assembly failed, rolling back the envelope build.', [
                    'flow' => 'envelope-build',
                    'envelope_id' => $envelope->getKey(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]
            );

            throw $e;
        } finally {
            $assembly->cleanup();
        }
    }

    /**
     * Claim a pending envelope for assembly.
     *
     * @throws LogicException When another run already claimed the envelope.
     */
    protected function claim(SiiDteEnvelope $envelope): void
    {
        $claimed = SiiDteEnvelope::query()
            ->whereKey($envelope->getKey())
            ->where('status', EnvelopeStatus::Pending)
            ->update(['status' => EnvelopeStatus::Assembling]);

        if ($claimed < 1 && $envelope->status !== EnvelopeStatus::Assembling) {
            throw new LogicException('Only pending DTE envelopes may be assembled.');
        }

        $envelope->forceFill(['status' => EnvelopeStatus::Assembling]);
    }
}
