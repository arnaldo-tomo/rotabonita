<?php

declare(strict_types=1);

namespace Rotabonita;

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Arr;

/**
 * Rotabonita URL Generator.
 *
 * Intercepts Laravel's `route()` calls replacing numeric IDs with public tokens.
 */
final class RotabonitaUrlGenerator extends UrlGenerator
{
    /**
     * Replaces standard integers and keys fetched via getRouteKey() with obfuscated tokens.
     */
    public function formatParameters($parameters): array
    {
        $parameters = Arr::wrap($parameters);
        $generator = app(TokenGenerator::class);

        foreach ($parameters as $key => $parameter) {
            if ($parameter instanceof Model && $parameter instanceof UrlRoutable) {
                // Determine if model uses an incrementing integer ID
                if ($parameter->getKeyType() === 'int' && $parameter->getIncrementing()) {
                    $parameters[$key] = $generator->encode($parameter->getKey());
                    continue;
                }
                $parameters[$key] = $parameter->getRouteKey();
            } 
            // Encode ANY raw integer found in route parameters! This guarantees SPA/Inertia compatibility
            // if Developer passes explicit numbers `route('users', 16)` missing Model type hint!
            elseif (is_int($parameter) || (is_string($parameter) && ctype_digit($parameter))) {
                $parameters[$key] = $generator->encode($parameter);
            } 
            // Fallback for native string keys natively handled by Laravel UrlGenerator
            elseif ($parameter instanceof UrlRoutable) {
                $parameters[$key] = $parameter->getRouteKey();
            }
        }

        return $parameters;
    }
}
