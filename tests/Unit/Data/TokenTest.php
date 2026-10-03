<?php

namespace Tests\Unit\Data;

use Laragear\Dte\Data\Token;
use Tests\TestCase;
use function json_encode;
use function time;

class TokenTest extends TestCase
{
    public function test_creates_from_string_with_ttl(): void
    {
        $token = Token::make('foo-bar', time() + 60);

        static::assertSame('foo-bar', $token->value);
        static::assertGreaterThan(time(), $token->expiresAt);
    }

    public function test_array(): void
    {
        $array = [
            'value' => 'foo',
            'expires_at' => $time = time() + 60,
        ];

        $token = Token::fromArray($array);

        static::assertSame('foo', $token->value);
        static::assertSame($time, $token->expiresAt);
    }

    public function test_json(): void
    {
        $token = Token::fromJson($json = '{"value":"foo","expires_at":'.time() + 60 .'}');

        static::assertSame('foo', $token->value);
        static::assertGreaterThan(time(), $token->expiresAt);

        static::assertSame($json, $token->toJson());
        static::assertSame($json, json_encode($token));
    }

    public function test_expired_at(): void
    {
        $token = Token::make('foo', 0);

        static::assertTrue($token->isExpired());
        static::assertFalse($token->isNotExpired());

        $token = Token::make('foo', time() + 60);

        static::assertFalse($token->isExpired());
        static::assertTrue($token->isNotExpired());
    }
}
