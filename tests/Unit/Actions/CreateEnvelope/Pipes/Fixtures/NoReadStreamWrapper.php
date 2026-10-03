<?php

namespace Tests\Unit\Actions\CreateEnvelope\Pipes\Fixtures;

class NoReadStreamWrapper
{
    public $context;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        if (str_contains($mode, 'r')) {
            return false;
        }

        return true;
    }

    public function stream_write($data)
    {
        return strlen($data);
    }

    public function stream_read($count)
    {
        return false;
    }

    public function stream_eof()
    {
        return true;
    }

    public function stream_stat()
    {
        return [];
    }

    public function stream_lock()
    {
        return true;
    }
}
