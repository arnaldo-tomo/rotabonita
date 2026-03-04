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
    /**
     * URL-safe alphabet: 64 characters (A-Z, a-z, 0-9, _, -).
     */
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';

    /**
     * Default token length (matches YouTube's public video IDs).
     */
    public const TOKEN_LENGTH = 11;

    public function __construct(
        private readonly string $appKey
    ) {}

    /**
     * Encode a raw numeric ID into an 11-char token directly given the Model class namespace.
     *
     * @param  int|string  $id
     * @param  class-string<Model>  $modelClass
     * @return string
     */
    public function encodeId(int|string $id, string $modelClass): string
    {
        $hashids = $this->getHasherForClass($modelClass);

        return $hashids->encode((int) $id);
    }

    /**
     * Encode a model's numeric primary key into an 11-char token.
     *
     * @param  Model  $model
     * @return string
     */
    public function encode(Model $model): string
    {
        return $this->encodeId($model->getKey(), get_class($model));
    }

    /**
     * Decode an 11-char token back to a numeric ID for a specific model class.
     *
     * @param  string  $token
     * @param  class-string<Model> $modelClass
     * @return int|null
     */
    public function decode(string $token, string $modelClass): ?int
    {
        $hashids = $this->getHasherForClass($modelClass);

        $decoded = $hashids->decode($token);

        return $decoded[0] ?? null;
    }

    /**
     * Ensure tokens are unique per Eloquent Model by appending the class name
     * to the salt. This guarantees Post #1 and User #1 produce different tokens.
     */
    private function getHasherForClass(string $modelClass): Hashids
    {
        $salt = substr(md5($this->appKey . $modelClass), 0, 16);

        return new Hashids($salt, self::TOKEN_LENGTH, self::ALPHABET);
    }
}
