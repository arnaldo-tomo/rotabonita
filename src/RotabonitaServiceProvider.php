<?php

declare(strict_types=1);

namespace Rotabonita;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Str;
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
     * 
     * If a raw numeric ID is accessed directly (e.g. from an SPA/Inertia frontend), 
     * we intercept and throw a 308 redirect to the obfuscated token URL.
     */
    private function registerRouteDecoding(): void
    {
        Event::listen(RouteMatched::class, function (RouteMatched $event) {
            $route = $event->route;
            $request = $event->request;
            $parameters = $route->parameters();

            // Discovers route parameters requesting Eloquent Model Types in Signature
            $signatureParameters = $route->signatureParameters(['subClassOf' => Model::class]);
            
            if (empty($signatureParameters)) {
                return;
            }

            $needsRedirect = false;
            $newParameters = $parameters;

            /** @var TokenGenerator $generator */
            $generator = $this->app->make(TokenGenerator::class);

            foreach ($signatureParameters as $parameter) {
                // Determine parameter name mapped according to original framework heuristics
                $paramName = $this->getParameterName($parameter->getName(), $parameters);

                if ($paramName && isset($parameters[$paramName])) {
                    $value = $parameters[$paramName];
                    $modelClass = $parameter->getType() ? $parameter->getType()->getName() : null;

                    if (!$modelClass) {
                        continue;
                    }

                    // 1. If an SPA sends a raw numeric ID (e.g /events/11), intercept and auto-redirect
                    if (is_numeric($value) && $request->isMethod('GET')) {
                        $newParameters[$paramName] = $generator->encodeId($value, $modelClass);
                        $needsRedirect = true;
                    } 
                    // 2. If it's already an obfuscated token, safely decode it for Backend DB resolution
                    elseif (is_string($value) && $this->isObfuscatedToken($value)) {
                        $decodedId = $generator->decode($value, $modelClass);
                        if ($decodedId !== null) {
                            $route->setParameter($paramName, $decodedId);
                        }
                    }
                }
            }

            // Perform an early SPA-friendly Redirect before resolving Controller Action
            if ($needsRedirect && $route->getName()) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    redirect()->route($route->getName(), $newParameters + $request->query(), 308)
                );
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

    /**
     * Return the parameter name if it exists in the array of parameters.
     * Replicates Laravel's protected ImplicitRouteBinding::getParameterName() mechanism.
     */
    private function getParameterName(string $name, array $parameters): ?string
    {
        if (array_key_exists($name, $parameters)) {
            return $name;
        }

        if (array_key_exists($snakedName = Str::snake($name), $parameters)) {
            return $snakedName;
        }

        return null;
    }
}
