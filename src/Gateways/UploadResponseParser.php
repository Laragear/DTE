<?php

namespace Laragear\Dte\Gateways;

use RuntimeException;

class UploadResponseParser
{
    /**
     * Extract the TRACKID and STATUS XML from an SII upload response body.
     */
    public function parseTrackId(string $responseBody): string
    {
        if (preg_match('/<TRACKID>(\d+)<\/TRACKID>/i', $responseBody, $matches)) {
            return $matches[1];
        }

        if (preg_match('/<STATUS>([^<]+)<\/STATUS>/i', $responseBody, $matches) && $matches[1] !== '0') {
            throw new RuntimeException('SII Upload rejected the envelope: '.$matches[1]);
        }

        throw new RuntimeException('SII Upload response did not contain a valid TrackID.');
    }
}
