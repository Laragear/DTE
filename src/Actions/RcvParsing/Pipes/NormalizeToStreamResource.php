<?php

namespace Laragear\Dte\Actions\RcvParsing\Pipes;

use Closure;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Laragear\Dte\Actions\RcvParsing\ParsingContext;
use Laragear\Dte\Proxies\StreamProxy;
use RuntimeException;
use SplFileInfo;

/**
 * Normalizes the source input into a PHP active stream safely.
 */
class NormalizeToStreamResource
{
    /**
     * Create a new Normalize to Stream Resource instance.
     */
    public function __construct(
        protected StreamProxy $stream,
        protected Filesystem $files,
    ) {
        //
    }

    /**
     * Handle the incoming RCV Parsing.
     *
     * @param  Closure(ParsingContext):ParsingContext  $next
     */
    public function handle(ParsingContext $context, Closure $next): ParsingContext
    {
        $context->stream = $this->resolveStream($context->source);

        if ($context->stream === false) {
            throw new RuntimeException('Failed applying stream protocols on the provided RCV file source.');
        }

        return $next($context);
    }

    /**
     * Resolve the source payload into a standard stream.
     */
    protected function resolveStream(mixed $payload): mixed
    {
        return match (true) {
            is_resource($payload) => $payload,
            $payload instanceof SplFileInfo => $this->streamFromSplFileInfo($payload),
            is_string($payload) && $this->files->isFile($payload) => $this->stream->fopen($payload, 'r'),
            is_string($payload) => $this->streamFromString($payload),
            default => throw new InvalidArgumentException('Unsupported parsing source format provided.'),
        };
    }

    /**
     * Parse path from a valid SplFileInfo structure.
     */
    protected function streamFromSplFileInfo(SplFileInfo $payload): mixed
    {
        $path = $payload->getRealPath();

        if ($path === false) {
            throw new InvalidArgumentException('Provided SplFileInfo is invalid or missing.');
        }

        return $this->stream->fopen($path, 'r');
    }

    /**
     * Write raw string binary content into a native memory block safely.
     */
    protected function streamFromString(string $payload): mixed
    {
        $stream = $this->stream->fopen('php://temp', 'r+');

        if ($stream === false) {
            return false;
        }

        $this->stream->fwrite($stream, $payload);
        $this->stream->fseek($stream, 0);

        return $stream;
    }
}
