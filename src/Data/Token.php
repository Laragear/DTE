<?php

namespace Laragear\Dte\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\DateFactory;
use JsonSerializable;

use function json_decode;
use function json_encode;

class Token implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * Create a new Token instance.
     */
    public function __construct(
        public string $value,
        public int $expiresAt,
    ) {
        //
    }

    /**
     * Determine whether the token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt === 0
            || app(DateFactory::class)->now()->getTimestamp() >= $this->expiresAt;
    }

    /**
     * Determine whether the toke has not expired.
     */
    public function isNotExpired(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Create a token that expires in the given seconds.
     */
    public static function make(string $token, int $timestamp): static
    {
        return new static($token, $timestamp);
    }

    /**
     * Create a new Token from an array.
     */
    public static function fromArray(array $array): static
    {
        return static::make($array['value'], $array['expires_at']);
    }

    /**
     * Create a new Token from a JSON string.
     */
    public static function fromJson(string $json): static
    {
        return static::fromArray(json_decode($json, true, 2));
    }

    /**
     * @inheritDoc
     *
     * @return array{value: string, expires_at: int}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'expires_at' => $this->expiresAt,
        ];
    }

    /**
     * @inheritDoc
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @inheritDoc
     *
     * @return array{value: string, expires_at: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
