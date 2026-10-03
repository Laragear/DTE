<?php

namespace Laragear\Dte\Mailbox\Drivers;

use Google\Client;
use Google\Service\Gmail;

class GoogleWorkspaceClientFactory
{
    /**
     * Build an authenticated Gmail service from the given config and client.
     *
     * @param  array<string, string>  $config
     */
    public function make(array $config, Client $client): Gmail
    {
        $client->setClientId($config['client_id'] ?? '');
        $client->setClientSecret($config['client_secret'] ?? '');
        $client->refreshToken($config['refresh_token'] ?? '');

        return new Gmail($client);
    }
}
