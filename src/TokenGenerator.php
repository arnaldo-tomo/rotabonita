<?php

declare(strict_types=1);

namespace Rotabonita;

use Hashids\Hashids;
use Illuminate\Database\Eloquent\Model;

/**
 * NanoID-style URL-safe token generator using Hashids.
 *
 * It transparently obfuscates numeric auto-incrementing IDs into 11-char strings
 * mimicking YouTube video IDs, and predictably decodes them back.
 */
final class TokenGenerator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';
    public const TOKEN_LENGTH = 11;

    private Hashids $hashids;

    public function __construct(string $appKey)
    {
        // Global salt to handle obfuscation uniformly across all models and integer parameters
        $salt = substr(md5($appKey . 'rotabonita_global_salt'), 0, 16);
        $this->hashids = new Hashids($salt, self::TOKEN_LENGTH, self::ALPHABET);
    }

    /**
     * Encode a raw numeric integer or an Eloquent model.
     *
     * @param  int|string|Model  $target
     * @return string
     */
    public function encode(int|string|Model $target): string
    {
        $id = $target instanceof Model ? $target->getKey() : $target;

        return $this->hashids->encode((int) $id);
    }

    /**
     * Decode an 11-char token back to a numeric ID.
     *
     * @param  string  $token
     * @return int|null
     */
    public function decode(string $token): ?int
    {
        $decoded = $this->hashids->decode($token);

        return $decoded[0] ?? null;
    }
}
