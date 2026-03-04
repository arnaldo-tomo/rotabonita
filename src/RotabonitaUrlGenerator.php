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
     * Replaces standard keys natively fetched via getRouteKey() with obfuscated tokens.
     */
    public function formatParameters($parameters): array
    {
        $parameters = Arr::wrap($parameters);

        $generator = null;

        foreach ($parameters as $key => $parameter) {
            if ($parameter instanceof Model && $this->shouldObfuscate($parameter)) {
                $generator ??= app(TokenGenerator::class);
                // Substitute numeric key for secure Hashids 11-char string
                $parameters[$key] = $generator->encode($parameter);
            } elseif ($parameter instanceof UrlRoutable) {
                // Default fallback for unmatched models or non-integer models (e.g UUIDs).
                $parameters[$key] = $parameter->getRouteKey();
            }
        }

        return $parameters;
    }

    /**
     * Determines if a model primary key is eligible for Obfuscation.
     */
    private function shouldObfuscate(Model $model): bool
    {
        // We solely obfuscate integers since Hashids algorithms rely tightly on numbers.
        return $model->getKeyType() === 'int'
            && $model->getIncrementing()
            && !empty($model->getKey());
    }
}
