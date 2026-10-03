<?php

namespace Tests\Unit\Mailbox\Drivers;

use Google\Client;
use Google\Service\Gmail;
use Laragear\Dte\Mailbox\Drivers\GoogleWorkspaceClientFactory;
use Tests\TestCase;

class GoogleWorkspaceClientFactoryTest extends TestCase
{
    public function test_make_configures_client_and_returns_gmail(): void
    {
        $client = $this->createMock(Client::class);

        $client->expects(static::once())
            ->method('setClientId')
            ->with('my-client-id');

        $client->expects(static::once())
            ->method('setClientSecret')
            ->with('my-client-secret');

        $client->expects(static::once())
            ->method('refreshToken')
            ->with('my-refresh-token');

        $factory = new GoogleWorkspaceClientFactory;

        $gmail = $factory->make([
            'client_id' => 'my-client-id',
            'client_secret' => 'my-client-secret',
            'refresh_token' => 'my-refresh-token',
        ], $client);

        static::assertInstanceOf(Gmail::class, $gmail);
    }

    public function test_make_defaults_empty_strings_when_config_keys_missing(): void
    {
        $client = $this->createMock(Client::class);

        $client->expects(static::once())
            ->method('setClientId')
            ->with('');

        $client->expects(static::once())
            ->method('setClientSecret')
            ->with('');

        $client->expects(static::once())
            ->method('refreshToken')
            ->with('');

        $factory = new GoogleWorkspaceClientFactory;

        $factory->make([], $client);
    }
}
