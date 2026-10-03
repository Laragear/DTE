<?php

namespace Tests\Unit\Mailbox\Drivers;

use Laragear\Dte\Mailbox\Drivers\Microsoft365ClientFactory;
use Microsoft\Graph\GraphServiceClient;
use Tests\TestCase;

class Microsoft365ClientFactoryTest extends TestCase
{
    public function test_make_returns_graph_service_client(): void
    {
        $factory = new Microsoft365ClientFactory;

        $client = $factory->make([
            'tenant_id' => 'my-tenant-id',
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
        ]);

        static::assertInstanceOf(GraphServiceClient::class, $client);
    }

    public function test_make_passes_config_values_to_client_credential_context(): void
    {
        $factory = new Microsoft365ClientFactory;

        $client = $factory->make([
            'tenant_id' => 'tenant-123',
            'client_id' => 'client-456',
            'client_secret' => 'secret-789',
        ]);

        static::assertInstanceOf(GraphServiceClient::class, $client);
    }
}
