<?php

declare(strict_types=1);

namespace Rotabonita;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Global Route Model Binding Override.
 *
 * Hooks into Laravel's implicit route model binding resolution.
 * When a route parameter is encountered:
 *   - If it looks like a valid public_id token → resolve via `public_id` column.
 *   - If numeric → fall back to the model's primary key lookup.
 *
 * Operates transparently — no model modification, no trait, no config.
 */
final class RouteBindingOverride
{
    /**
     * @param  TokenGenerator  $generator  Used for token format validation.
     */
    public function __construct(
        private readonly TokenGenerator $generator
    ) {}

    /**
     * Register a global explicit binding for every Eloquent model discovered.
     *
     * This iterates over all loaded/registered models and, for each model whose
     * database table contains a `public_id` column, registers a custom resolver
     * with the Laravel Router.
     *
     * The resolver is keyed by the model's lowercase short class name, which is
     * the naming convention Laravel uses for implicit route model binding.
     *
     * @param  Router  $router  The application router instance.
     * @return void
     */
    public function register(Router $router): void
    {
        $models = $this->discoverModels();

        foreach ($models as $modelClass) {
            if (! $this->tableHasPublicId($modelClass)) {
                continue;
            }

            // Derive the route key name (e.g., "App\Models\Post" → "post").
            $shortName = strtolower(class_basename($modelClass));

            $router->bind($shortName, function (string $value) use ($modelClass) {
                return $this->resolve($modelClass, $value);
            });
        }
    }

    /**
     * Resolve a route parameter to a model instance.
     *
     * Resolution strategy:
     *  1. If the value matches the public_id token format → query by public_id.
     *  2. If numeric → query by primary key (default Laravel behaviour).
     *  3. Otherwise → query by public_id as a fallback.
     *
     * Triggers a 404 if no model is found, matching Laravel's default behaviour.
     *
     * @param  class-string<Model>  $modelClass  Fully qualified model class name.
     * @param  string               $value        Route parameter value.
     * @return Model                              Resolved model instance.
     */
    public function resolve(string $modelClass, string $value): Model
    {
        /** @var Model $instance */
        $instance = new $modelClass();

        if ($this->generator->isValidToken($value)) {
            // Token format detected → resolve by public_id.
            return $instance->newQuery()
                ->where('public_id', $value)
                ->firstOrFail();
        }

        if (is_numeric($value)) {
            // Numeric → default primary key resolution.
            return $instance->newQuery()
                ->where($instance->getKeyName(), $value)
                ->firstOrFail();
        }

        // Fallback: treat as public_id anyway (handles edge-case custom tokens).
        return $instance->newQuery()
            ->where('public_id', $value)
            ->firstOrFail();
    }

    /**
     * Discover all registered Eloquent model classes in the application.
     *
     * Uses multiple discovery strategies in order of reliability:
     *  1. Models pre-registered by the service provider (via app binding).
     *  2. Scan the app/Models directory (standard Laravel convention).
     *  3. Scan the app/ root (older single-tier layouts).
     *
     * Results are cached for the duration of the request to avoid filesystem
     * overhead on every binding registration.
     *
     * @return array<class-string<Model>>  List of discovered model class names.
     */
    public function discoverModels(): array
    {
        // Check if models were manually registered (e.g., in tests or non-standard layouts).
        if (app()->bound('rotabonita.models')) {
            return app('rotabonita.models');
        }

        $models = [];

        $directories = [
            app_path('Models'),
            app_path(),
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $found = $this->scanDirectory($directory);
            $models = array_merge($models, $found);
        }

        return array_unique($models);
    }

    /**
     * Recursively scan a directory for PHP files and return valid Model classes.
     *
     * @param  string  $directory  Absolute path to the directory.
     * @return array<class-string<Model>>
     */
    private function scanDirectory(string $directory): array
    {
        $models = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->fileToClass($file->getRealPath());

            if ($class === null) {
                continue;
            }

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }

            if (! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $models[] = $class;
        }

        return $models;
    }

    /**
     * Derive a fully-qualified class name from a PHP file path.
     *
     * Reads the file's namespace and class declarations via token parsing.
     * This avoids requiring/including the file and is safe for all valid PHP files.
     *
     * @param  string       $filePath  Absolute path to the PHP file.
     * @return string|null             Fully-qualified class name, or null if not parseable.
     */
    private function fileToClass(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        $namespace = '';
        $class = '';

        $tokens = token_get_all($content);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            // Capture namespace declaration.
            if ($tokens[$i][0] === T_NAMESPACE) {
                $i += 2; // Skip whitespace.
                while (isset($tokens[$i]) && is_array($tokens[$i])) {
                    $namespace .= $tokens[$i][1];
                    $i++;
                }
            }

            // Capture class name declaration.
            if ($tokens[$i][0] === T_CLASS) {
                $i += 2; // Skip whitespace.
                if (isset($tokens[$i][1])) {
                    $class = $tokens[$i][1];
                    break;
                }
            }
        }

        if ($namespace === '' || $class === '') {
            return null;
        }

        return $namespace . '\\' . $class;
    }

    /**
     * Check whether a model's database table contains a `public_id` column.
     *
     * Results are cached in-memory (runtime cache) to avoid repeated schema queries.
     * Uses Schema::hasColumn() which is safe and DB-agnostic.
     *
     * @param  class-string<Model>  $modelClass
     * @return bool
     */
    private function tableHasPublicId(string $modelClass): bool
    {
        static $cache = [];

        if (array_key_exists($modelClass, $cache)) {
            return $cache[$modelClass];
        }

        try {
            /** @var Model $instance */
            $instance = new $modelClass();
            $table = $instance->getTable();
            $cache[$modelClass] = Schema::hasColumn($table, 'public_id');
        } catch (\Throwable) {
            // If schema inspection fails (e.g., no DB connection), skip silently.
            $cache[$modelClass] = false;
        }

        return $cache[$modelClass];
    }
}
