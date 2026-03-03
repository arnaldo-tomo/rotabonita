# Rotabonita

**Zero-configuration Laravel package that replaces numeric route IDs with YouTube-style public tokens.**

[![Latest Stable Version](https://poser.pugx.org/arnaldo-tomo/rotabonita/v/stable)](https://packagist.org/packages/arnaldo-tomo/rotabonita)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-10%20%7C%2011-red)](https://laravel.com)

Install it. That's it.

Your routes go from:
```
/posts/42 → /posts/BYPWtH2qYos
```

No traits. No model changes. No config publishing. No manual route edits.

---

## Requirements

| Dependency | Version    |
|------------|------------|
| PHP        | ^8.1       |
| Laravel    | ^10.0 or ^11.0 |

---

## Installation

### 1. Require via Composer

```bash
composer require arnaldo-tomo/rotabonita
```

Auto-discovery handles registration automatically. No manual provider registration needed.

---

### 2. Add `public_id` to Your Tables

Publish the migration stub:

```bash
php artisan vendor:publish --tag=rotabonita-migrations
```

This copies a stub migration to `database/migrations/`. Open it and **replace `your_table_name`** with your actual table (e.g., `posts`):

```php
protected string $table = 'posts';
```

Then run:

```bash
php artisan migrate
```

Repeat for each table you want to expose via public tokens.

---

### 3. Done

Create a new model record as usual:

```php
$post = Post::create(['title' => 'Hello World']);

echo $post->public_id; // "BYPWtH2qYos"
```

Routes resolve automatically:

```php
// web.php
Route::get('/posts/{post}', [PostController::class, 'show']);

// In your browser: GET /posts/BYPWtH2qYos
// → $post is resolved with WHERE public_id = 'BYPWtH2qYos'
```

URL generation uses `public_id` automatically:

```php
route('posts.show', $post); // → https://example.com/posts/BYPWtH2qYos
```

---

## How It Works

### Token Format

Tokens are **11 characters** using the URL-safe alphabet `[A-Za-z0-9_-]` (64 characters).  
Total possible combinations: **64¹¹ ≈ 7.4 × 10¹⁹** — collision probability is effectively zero.

The generator uses `random_bytes()` with rejection sampling over a 64-character alphabet (a power of 2), which produces **zero modulo bias**.

### Token Generation (Automatic)

Rotabonita registers a global `Model::creating()` listener. On every model creation:

1. Checks if the model's table has a `public_id` column (cached per-table).
2. If yes and `public_id` is not already set, generates a unique token.
3. Retries on the (astronomically unlikely) event of a collision — up to 10 times.

### Route Model Binding Override (Automatic)

Rotabonita registers a custom `Router::bind()` resolver for every qualifying model (detected by scanning `app/Models/`). When a route parameter is resolved:

| Parameter value | Resolution |
|-----------------|------------|
| Matches token format (`[A-Za-z0-9_-]{11}`) | `WHERE public_id = ?` |
| Numeric | `WHERE id = ?` (default Laravel behaviour) |
| Other | `WHERE public_id = ?` (fallback) |

If no record is found, a `ModelNotFoundException` is thrown (→ HTTP 404), identical to Laravel's default behaviour.

---

## Backfilling Existing Records

If you added `public_id` to a table that already has rows, back-fill them with a simple Tinker command:

```php
php artisan tinker

>>> App\Models\Post::whereNull('public_id')->each(function ($post) {
...     $gen = app(\Rotabonita\TokenGenerator::class);
...     $post->public_id = $gen->generateUnique($post);
...     $post->save();
... });
```

---

## Advanced: Manual Model Registration

If your models live outside `app/Models/` or `app/`, register them manually in a service provider:

```php
// In AppServiceProvider::register()
$this->app->bind('rotabonita.models', fn() => [
    \App\Domain\Blog\Post::class,
    \App\Domain\Commerce\Product::class,
]);
```

---

## Advanced: Custom Token Length

By default tokens are 11 characters. To use a different length, extend `TokenGenerator`:

```php
use Rotabonita\TokenGenerator;

class MyTokenGenerator extends TokenGenerator
{
    public function generate(int $length = 16): string
    {
        return parent::generate($length);
    }
}
```

Then rebind in your service provider:

```php
$this->app->singleton(TokenGenerator::class, fn() => new MyTokenGenerator());
```

---

## Security Considerations

- **Never expose primary keys** (`id`) in your URLs. Rotabonita is designed for exactly this.
- Tokens are not sequential and carry no information about the record count, creation time, or ordering.
- The `public_id` column has a **database-level UNIQUE constraint** — enforced even if the application layer fails.

---

## Package Structure

```
rotabonita/
├── composer.json
├── README.md
├── database/
│   └── migrations/
│       └── add_public_id_to_table.php.stub
└── src/
    ├── RotabonitaServiceProvider.php   # Auto-discovered. Wires everything.
    ├── TokenGenerator.php              # Cryptographic NanoID-style generator.
    └── RouteBindingOverride.php        # Global route model binding + model discovery.
```

---

## Example: Full Route Setup

```php
// routes/web.php
use App\Http\Controllers\PostController;

Route::resource('posts', PostController::class);
```

```php
// app/Http/Controllers/PostController.php
public function show(Post $post): View
{
    // $post is already resolved — no findOrFail() needed.
    return view('posts.show', compact('post'));
}
```

```php
// In any Blade template:
<a href="{{ route('posts.show', $post) }}">
    Read "{{ $post->title }}"
</a>
{{-- renders: /posts/BYPWtH2qYos --}}
```

---

## Compatibility

| Laravel | PHP   | Status  |
|---------|-------|---------|
| 10.x    | 8.1+  | ✅ Supported |
| 11.x    | 8.2+  | ✅ Supported |

---

## License

MIT © [Arnaldo Tomo](https://github.com/arnaldo-tomo)
