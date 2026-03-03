<?php

declare(strict_types=1);

namespace Rotabonita;

use Illuminate\Database\Eloquent\Model;

/**
 * NanoID-style URL-safe token generator.
 *
 * Generates cryptographically strong, URL-safe tokens using a custom
 * alphabet identical to YouTube's video ID format. No external dependencies.
 */
final class TokenGenerator
{
    /**
     * URL-safe alphabet: A-Z, a-z, 0-9, underscore, hyphen.
     * 64 characters total — a power of 2, enabling bias-free generation.
     */
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';

    /**
     * Default token length (matches YouTube's public video IDs).
     */
    private const DEFAULT_LENGTH = 11;

    /**
     * Maximum number of attempts before giving up on collision avoidance.
     */
    private const MAX_ATTEMPTS = 10;

    /**
     * Generate a single cryptographically random, URL-safe token.
     *
     * Uses the rejection sampling (unbiased) method over a 64-char alphabet.
     * The mask is 0x3F (63), since 64 = 2^6, so no modulo bias occurs.
     *
     * @param  int  $length  Desired token length.
     * @return string        Generated token.
     *
     * @throws \RuntimeException If secure random bytes cannot be generated.
     */
    public function generate(int $length = self::DEFAULT_LENGTH): string
    {
        $alphabetSize = strlen(self::ALPHABET);
        $mask = $alphabetSize - 1; // 0x3F = 63, since alphabetSize = 64

        $token = '';
        // We request more bytes than needed to reduce re-rolls in the loop.
        $bytesNeeded = (int) ceil($length * 1.3);

        while (strlen($token) < $length) {
            $randomBytes = random_bytes($bytesNeeded);

            for ($i = 0; $i < $bytesNeeded && strlen($token) < $length; $i++) {
                $index = ord($randomBytes[$i]) & $mask;
                $token .= self::ALPHABET[$index];
            }
        }

        return $token;
    }

    /**
     * Generate a unique token for a given Eloquent model, retrying on collision.
     *
     * Checks database uniqueness against the `public_id` column before returning.
     * In practice, collisions are astronomically rare (alphabet^length = 64^11 ≈ 7.4×10^19).
     *
     * @param  Model  $model    The Eloquent model instance needing a token.
     * @param  int    $length   Desired token length.
     * @return string           A token guaranteed to be unique in the model's table.
     *
     * @throws \RuntimeException If a unique token cannot be generated within MAX_ATTEMPTS.
     */
    public function generateUnique(Model $model, int $length = self::DEFAULT_LENGTH): string
    {
        $attempts = 0;

        do {
            if ($attempts >= self::MAX_ATTEMPTS) {
                throw new \RuntimeException(
                    sprintf(
                        '[Rotabonita] Failed to generate a unique public_id for [%s] after %d attempts.',
                        get_class($model),
                        self::MAX_ATTEMPTS
                    )
                );
            }

            $token = $this->generate($length);
            $attempts++;
        } while ($this->tokenExists($model, $token));

        return $token;
    }

    /**
     * Check whether a given token already exists in the model's table.
     *
     * @param  Model   $model  The Eloquent model.
     * @param  string  $token  The candidate token.
     * @return bool            True if the token is already taken.
     */
    private function tokenExists(Model $model, string $token): bool
    {
        return $model->newQueryWithoutScopes()
            ->where('public_id', $token)
            ->exists();
    }

    /**
     * Validate whether a given string matches the public_id token format.
     *
     * This is used by the route binder to determine if a route parameter
     * should be resolved via public_id lookup vs. a plain numeric ID lookup.
     *
     * @param  string  $value   The route parameter value.
     * @param  int     $length  Expected token length.
     * @return bool             True if the value looks like a valid token.
     */
    public function isValidToken(string $value, int $length = self::DEFAULT_LENGTH): bool
    {
        if (strlen($value) !== $length) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9_\-]+$/', $value);
    }
}
