<?php

declare(strict_types=1);

namespace Rotabonita;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Rotabonita Service Provider.
 *
 * Bootstraps the entire package with zero configuration:
 *  1. Registers the TokenGenerator singleton.
 *  2. Registers the RouteBindingOverride singleton.
 *  3. Hooks into Eloquent model creation to auto-assign public_id tokens.
 *  4. Overrides route model binding globally for all qualifying models.
 *  5. Publishes the migration stub.
 */
final class RotabonitaServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings into the service container.
     *
     * This runs before boot(), so services registered here are available
     * to other providers during their own boot phase.
     */
    public function register(): void
    {
        $this->app->singleton(TokenGenerator::class, fn () => new TokenGenerator());

        $this->app->singleton(RouteBindingOverride::class, function () {
            return new RouteBindingOverride(
                $this->app->make(TokenGenerator::class)
            );
        });
    }

    /**
     * Bootstrap package services.
     *
     * Execution order:
     *  1. Publish migration stubs.
     *  2. Register the global Eloquent "creating" listener for token generation.
     *  3. Register the route model binding overrides (deferred until after routing bootstrap).
     */
    public function boot(): void
    {
        $this->publishMigrations();
        $this->registerTokenListener();
        $this->registerRouteBindings();
    }

    /**
     * Publish the migration stub to the host application.
     *
     * Developers can run:
     *   php artisan vendor:publish --tag=rotabonita-migrations
     *
     * This creates a timestamped migration that adds `public_id` to any table.
     */
    private function publishMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__ . '/../database/migrations/' => database_path('migrations/')],
                'rotabonita-migrations'
            );
        }
    }

    /**
     * Register a global Eloquent "creating" listener.
     *
     * This listener fires on every model creation across the entire application.
     * It checks:
     *  1. Does the model's table have a `public_id` column?
     *  2. Is the `public_id` not already set?
     *
     * If both conditions are true, it generates a unique token and assigns it.
     * This approach requires NO trait, NO model modification.
     */
    private function registerTokenListener(): void
    {
        /** @var TokenGenerator $generator */
        $generator = $this->app->make(TokenGenerator::class);

        Model::creating(function (Model $model) use ($generator): void {
            // Skip if the table doesn't have a public_id column.
            // We use a static in-memory cache to avoid repeated Schema calls.
            if (! $this->modelHasPublicId($model)) {
                return;
            }

            // Skip if the developer has already set a public_id explicitly.
            if (! empty($model->public_id)) {
                return;
            }

            $model->public_id = $generator->generateUnique($model);
        });
    }

    /**
     * Register route model bindings for all qualifying Eloquent models.
     *
     * Uses `afterResolving` to defer registration until the Router is fully
     * booted, which ensures all routes from the application and other packages
     * are already registered before our bindings are applied.
     *
     * This also means the package is compatible with route caching.
     */
    private function registerRouteBindings(): void
    {
        $this->app->afterResolving(Router::class, function (Router $router): void {
            /** @var RouteBindingOverride $override */
            $override = $this->app->make(RouteBindingOverride::class);
            $override->register($router);
        });
    }

    /**
     * Check whether a model's database table contains the `public_id` column.
     *
     * Caches results in a static array keyed by table name to avoid redundant
     * Schema::hasColumn() calls on every model creation event.
     *
     * @param  Model  $model
     * @return bool
     */
    private function modelHasPublicId(Model $model): bool
    {
        static $cache = [];

        $table = $model->getTable();

        if (! array_key_exists($table, $cache)) {
            try {
                $cache[$table] = Schema::hasColumn($table, 'public_id');
            } catch (\Throwable) {
                // Schema not reachable (e.g., during unit tests without DB).
                $cache[$table] = false;
            }
        }

        return $cache[$table];
    }
}
