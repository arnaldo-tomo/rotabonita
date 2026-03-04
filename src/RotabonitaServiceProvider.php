<?php

declare(strict_types=1);

namespace Rotabonita;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\ImplicitRouteBinding;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Rotabonita Service Provider.
 *
 * Bootstraps the entire package with ZERO configuration:
 *  1. Registers TokenGenerator using the application secret key.
 *  2. Replaces Laravel's UrlGenerator to obfuscate IDs in generated URLs.
 *  3. Listens to `RouteMatched` to decode obfuscated IDs before implicit resolution.
 */
final class RotabonitaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Instantiates encoder configured by App environment
        $this->app->singleton(TokenGenerator::class, function (Application $app) {
            $key = $app['config']->get('app.key', 'rotabonita-fallback-key');
            return new TokenGenerator($key);
        });

        // 2. Safely swap standard Route Generator overriding Laravel Defaults
        $this->registerUrlGenerator();
    }

    public function boot(): void
    {
        // 3. Registers Transparent Implicit Resolution Decoder
        $this->registerRouteDecoding();
    }

    /**
     * Intercepts `route(...)` rendering. Overrides UrlGenerator singleton so that any 
     * future service bindings utilize Rotabonita's URL parameters format mechanism.
     */
    private function registerUrlGenerator(): void
    {
        $this->app->extend('url', function (UrlGenerator $url, Application $app) {
            return new RotabonitaUrlGenerator(
                $app['router']->getRoutes(),
                $app['request'],
                $app['config']->get('app.asset_url')
            );
        });
    }

    /**
     * Traps resolution precisely after a Route explicitly matches but BEFORE parameters
     * are injected into controllers via SubstituteBindings.
     */
    private function registerRouteDecoding(): void
    {
        Event::listen(RouteMatched::class, function (RouteMatched $event) {
            $route = $event->route;
            $parameters = $route->parameters();

            // Discovers route parameters requesting Eloquent Model Types in Signature
            $signatureParameters = $route->signatureParameters(['subClassOf' => Model::class]);
            
            if (empty($signatureParameters)) {
                return;
            }

            /** @var TokenGenerator $generator */
            $generator = $this->app->make(TokenGenerator::class);

            foreach ($signatureParameters as $parameter) {
                // Determine parameter name mapped according to original framework heuristics
                $paramName = ImplicitRouteBinding::getParameterName($parameter->getName(), $parameters);

                if ($paramName && isset($parameters[$paramName])) {
                    $value = $parameters[$paramName];
                    $modelClass = $parameter->getType() ? $parameter->getType()->getName() : null;

                    // Execute decoder ONLY if parameter strictly resembles obfuscated tokens
                    if (is_string($value) && $modelClass && $this->isObfuscatedToken($value)) {
                        $decodedId = $generator->decode($value, $modelClass);
                        if ($decodedId !== null) {
                            // Flawlessly reverts route variable to typical numeric ID
                            // ensuring standard query executes normally behind-the-scenes
                            $route->setParameter($paramName, $decodedId);
                        }
                    }
                }
            }
        });
    }

    /**
     * Safety heuristic distinguishing ordinary string variables from encrypted tokens.
     */
    private function isObfuscatedToken(string $value): bool
    {
        if (strlen($value) !== TokenGenerator::TOKEN_LENGTH) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9_\-]+$/', $value);
    }
}
