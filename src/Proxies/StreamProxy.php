<?php

namespace Laragear\Dte\Proxies;

/** @internal */
class StreamProxy
{
    /**
     * Opens a file or URL.
     *
     * @return resource|false
     */
    public function fopen(string $filename, string $mode): mixed
    {
        return fopen($filename, $mode);
    }

    /**
     * Binary-safe file write.
     *
     * @param  resource  $stream
     */
    public function fwrite(mixed $stream, string $data): int|false
    {
        return fwrite($stream, $data);
    }

    /**
     * Seeks on a file pointer.
     */
    public function fseek(mixed $stream, int $offset, int $whence = SEEK_SET): int
    {
        return fseek($stream, $offset, $whence);
    }

    /**
     * Attach a filter to a stream.
     *
     * @param  resource  $stream
     * @return resource|false
     */
    public function appendFilter(
        mixed $stream,
        string $filterName,
        int $readWrite = STREAM_FILTER_READ,
        mixed $params = null,
    ): mixed {
        return stream_filter_append($stream, $filterName, $readWrite, $params);
    }

    /**
     * Gets line from file pointer and parse for CSV fields.
     *
     * @param  resource  $stream
     * @return array<int, string|null>|false|null
     */
    public function fgetcsv(
        mixed $stream,
        ?int $length = null,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
    ): array|false|null {
        // fgetcsv() length defaults to 0 (unlimited) in PHP < 8.0, otherwise it uses `null`.
        return fgetcsv($stream, $length, $separator, $enclosure, $escape);
    }

    /**
     * Closes an open file pointer.
     *
     * @param  resource  $stream
     */
    public function fclose(mixed $stream): bool
    {
        return fclose($stream);
    }
}
