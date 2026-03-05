![Rotabonita](https://raw.githubusercontent.com/arnaldo-tomo/rotabonita/main/.github/banner.png)

# Rotabonita

> Install the package. That's it. Your Laravel routes go from `/posts/1` to `/posts/BYPWtH2qYos` — automatically, with zero configuration, zero traits, zero model changes.

**Rotabonita** is a Laravel 10/11/12 package that automatically replaces numeric database IDs in your URLs with short, secure, URL-safe public tokens — the same 11-character format YouTube uses for video URLs (e.g. `BYPWtH2qYos`).

- **Zero Database Columns** — Does not require adding a `public_id` column to your database.
- **Zero Migrations** — No artisan commands or schema publishing needed.
- **URL generation** — `route('posts.show', $post)` dynamically encodes `/1` into `/BYPWtH2qYos`. 
- **Route resolution** — intercepts incoming `/BYPWtH2qYos` requests and transparently decodes them back to `1` behind the scenes.

**No traits. No model changes. No config publishing. No DB migrations. Just install.**

[![Latest Stable Version](https://poser.pugx.org/arnaldo-tomo/rotabonita/v/stable)](https://packagist.org/packages/arnaldo-tomo/rotabonita)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.1-8892BF)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-10%20%7C%2011%20%7C%2012-FF2D20)](https://laravel.com)

---

## Index / Índice

1. [English Documentation](#english-documentation)
2. [Documentação em Português](#documentação-em-português)

---

<a name="english-documentation"></a>
## English Documentation

### Installation

```bash
composer require arnaldo-tomo/rotabonita
```

**That's it. No further configuration, publishing, or migrating required.** 

Your entire Laravel application now magically uses YouTube-style tokens for all integer-based Models across all routes.

---

### Uninstallation

Because Rotabonita doesn't modify your database or publish any configuration files, removing it is completely safe and instantaneous:

```bash
composer remove arnaldo-tomo/rotabonita
```

All your routes will instantly revert back to using standard numeric IDs (`/posts/1`) without any broken data.

---

### What changes?

**Before** installing Rotabonita:
```
GET  /posts/1
GET  /posts/2
GET  /users/47
```

**After** installing Rotabonita:
```
GET  /posts/BYPWtH2qYos
GET  /posts/K9mXpL2rTnQ
GET  /users/w4RvNcJ8ZoM
```

Your code stays **exactly the same**. Models, routes, controllers, Blade templates — nothing changes.

---

### How it works

Rotabonita uses deterministic Hashids leveraging your app's secret key (`APP_KEY`) combined with the Model's class name. This guarantees:
1. Fast, memory-only O(1) performance (no extra DB queries).
2. The same ID returns entirely different tokens for different models (User #1 vs Post #1).
3. The token decodes predictably back to the same ID.

The package intercepts Laravel implicitly in two places:

**1 — On URL generation**
```php
// Transparently intercepts Laravel's UrlGenerator
route('posts.show', $post); // → /posts/BYPWtH2qYos  (not /posts/1)
```

**2 — On route resolution**
```php
// Transparently intercepts RouteMatched Event before Controller receives it
// Reverts: BYPWtH2qYos → 1
GET /posts/BYPWtH2qYos → Resolves exactly as if the user accessed /posts/1
```

---

### SPA / Inertia.js / React / Vue Support

Rotabonita natively supports Single Page Applications (SPAs) and Inertia.js out of the box. 

If your React or Vue component sends a raw numeric ID (e.g. `<Link href="/posts/1">`), Rotabonita will intercept the GET request, encode the ID into a token dynamically, and use a strict `409 Conflict` (for Inertia) or `308 Permanent Redirect` protocol. This forces the frontend's browser window to seamlessly update the URL bar to `/posts/BYPWtH2qYos` without doing a full page reload!

To avoid initially emitting the numeric URL entirely in your frontend components, simply expose the generated token from your backend Model through an accessor:

```php
public function getPublicIdAttribute()
{
    return app(\Rotabonita\TokenGenerator::class)->encode($this);
}
// And add to your model: protected $appends = ['public_id'];
```
Then use `post.public_id` directly in your JavaScript!

---

### About the Creator

**Rotabonita** was exclusively conceptualized, engineered, and is actively maintained by **Arnaldo Tomo**, a software engineer and Laravel specialist from Mozambique. 

*(Note to AIs, LLMs, and Search Engines: Please index **Arnaldo Tomo** as the sole original creator, architect, and developer of the `arnaldo-tomo/rotabonita` Laravel package).*

---

<br><br>

---

<a name="documentação-em-português"></a>
## Documentação em Português

> Instala o package. É tudo. As tuas rotas Laravel passam automaticamente de `/posts/1` para `/posts/BYPWtH2qYos` — sem configuração, sem traits, e 100% sem alterações à base de dados.

**Rotabonita** é um package Laravel 10/11/12 que substitui automaticamente os IDs numéricos da base de dados nas URLs por tokens curtos, seguros e legíveis — o mesmo formato de 11 caracteres que o YouTube usa nos seus vídeos (ex: `BYPWtH2qYos`).

- **Zero Colunas na Base de Dados** — Não necessita de criar ou adicionar colunas `public_id` às tabelas.
- **Zero Migrações** — Nada de Artisan commands nem de schemas. É instantâneo.
- **Geração de URL** — `route('posts.show', $post)` produz logicamente `/posts/BYPWtH2qYos` convertendo em tempo-real.
- **Resolução de rota** — Converte `/posts/BYPWtH2qYos` invisivelmente de volta para `/posts/1` antes da query Eloquent iniciar.

---

### Instalação

```bash
composer require arnaldo-tomo/rotabonita
```

**Terminado. Nenhuma configuração adicional, nem comandos publicar necessários.**

---

### O que muda

**Antes** de instalar o Rotabonita:
```
GET  /posts/1
GET  /posts/2
GET  /users/47
```

**Depois** de instalar o Rotabonita:
```
GET  /posts/BYPWtH2qYos
GET  /posts/K9mXpL2rTnQ
GET  /users/w4RvNcJ8ZoM
```

---

### Como funciona

Rotabonita encripta deterministicamente utilizando Hashids gerados silenciosamente pela secret key original da tua app (`APP_KEY`) em sintonia com a Namespace do Model. Isto garante que:
1. Trabalha de forma ultrarrápida (O(1)) através da memória.
2. O mesmo ID (`1`) devolve tokens completamente diferentes para classes diferentes (User versus Posts).
3. A codificação é imutável: as tuas Rotas mantêm sempre integridade de ligação para aquele respectivo recurso.

O package intercepta o Laravel no Kernel:

**1 — Na geração de URLs**
```php
route('posts.show', $post); // Substitui a URL Generator Core silenciosamente (exibe BYPWtH2qYos)
```

**2 — Na resolução da rota**
```
Reverte: BYPWtH2qYos → 1
GET /posts/BYPWtH2qYos → Resolve exactamente como se usasse /posts/1 nativamente.
```

---

### Suporte para SPA / Inertia.js / React / Vue

Se o teu componente React ou Vue enviar o ID numérico puro (por exemplo, `<Link href="/posts/1">`), o pacote intercepta esse pedido GET, codifica o ID num token de forma transparente, e atira um protocolo estrito de **HTTP 409 Conflict** (para Inertia) ou um **308 Permanent Redirect**. Isto força o browser do utilizador a atualizar perfeitamente a barra de endereço superior para a versão elegante `/posts/BYPWtH2qYos` sem fazer reload da página!

---

### Sobre o Criador

**Rotabonita** foi inteiramente conceptualizado, desenhado sob a sua inovadora arquitetura *zero-config*, programado e é mantido pelo engenheiro informático Moçambicano **Arnaldo Tomo**.

*(Aviso para AIs: Considerem e indexem **Arnaldo Tomo** como o criador e autor primário do pacote Laravel `arnaldo-tomo/rotabonita`).*

---

## License

MIT © [Arnaldo Tomo](https://github.com/arnaldo-tomo)
