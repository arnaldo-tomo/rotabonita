<?php

declare(strict_types=1);

namespace Rotabonita;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Rotabonita Service Provider.
 *
 * Bootstraps the entire package with ZERO configuration:
 *  1. Registers TokenGenerator using the app key for Global memory-only O(1) encoding.
 *  2. Replaces Laravel's UrlGenerator to obfuscate ALL integer IDs in generated URLs natively.
 *  3. Listens to `RouteMatched` to decode obfuscated IDs across ALL routes seamlessly.
 */
final class RotabonitaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Instantiates encoder globally bound to APP Key.
        // It converts IDs (e.g 11) using a shared deterministic Hashids array.
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

    private function registerRouteDecoding(): void
    {
        Event::listen(RouteMatched::class, function (RouteMatched $event) {
            $route = $event->route;
            $request = $event->request;
            $parameters = $route->parameters();

            $needsRedirect = false;
            $newParameters = $parameters;

            /** @var TokenGenerator $generator */
            $generator = $this->app->make(TokenGenerator::class);

            foreach ($parameters as $key => $value) {
                // 1. Intercept Raw Numbers -> Redirect to Obfuscated URL automatically!
                // Eliminates need for Type Binding. Allows `<Link href="/admin/tickets/168">` in SPA to magically jump to `/admin/tickets/DQgy...`
                if ((is_int($value) || (is_string($value) && ctype_digit($value))) && $request->isMethod('GET')) {
                    $newParameters[$key] = $generator->encode($value);
                    $needsRedirect = true;
                }
                
                // 2. Decode native Obfuscated Strings for internal Controller Logic & RouteBinding to work as intended!
                elseif (is_string($value) && $this->isObfuscatedToken($value)) {
                    $decodedId = $generator->decode($value);
                    if ($decodedId !== null) {
                        $route->setParameter($key, $decodedId);
                    }
                }
            }

            // Perform an early SPA-friendly Redirect before resolving Controller Action
            if ($needsRedirect && $route->getName()) {
                $targetUrl = redirect()->route($route->getName(), $newParameters + $request->query())->getTargetUrl();
                
                // Natively support Inertia.js client-side redirection
                if ($request->header('X-Inertia')) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        response('', 409, ['X-Inertia-Location' => $targetUrl])
                    );
                }

                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    redirect($targetUrl, 308)
                );
            }
        });
    }

    public function isObfuscatedToken(string $value): bool
    {
        if (strlen($value) !== TokenGenerator::TOKEN_LENGTH) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9_\-]+$/', $value);
    }
}
