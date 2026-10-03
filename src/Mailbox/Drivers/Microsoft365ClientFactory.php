<?php

namespace Laragear\Dte\Mailbox\Drivers;

use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;

class Microsoft365ClientFactory
{
    /**
     * Build an authenticated Microsoft Graph service client from the given config.
     *
     * @param  array<string, string>  $config
     */
    public function make(array $config): GraphServiceClient
    {
        $tokenRequestContext = new ClientCredentialContext(
            $config['tenant_id'] ?? '',
            $config['client_id'] ?? '',
            $config['client_secret'] ?? '',
        );

        return new GraphServiceClient($tokenRequestContext, ['https://graph.microsoft.com/.default']);
    }
}
