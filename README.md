# Rotabonita

**Laravel package que substitui automaticamente IDs numéricos nas URLs por tokens seguros no estilo YouTube — sem configuração, sem modificar models, sem mudar a forma de programar.**

[![Latest Stable Version](https://poser.pugx.org/arnaldo-tomo/rotabonita/v/stable)](https://packagist.org/packages/arnaldo-tomo/rotabonita)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.1-8892BF)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-10%20%7C%2011-FF2D20)](https://laravel.com)

---

## O problema

Por padrão, o Laravel expõe os IDs da base de dados directamente nas URLs:

```
https://meusite.com/posts/1
https://meusite.com/posts/2
https://meusite.com/users/47
```

Isto é problemático porque:

- Qualquer utilizador consegue saber **quantos registos** existem na tua base de dados
- É fácil **enumerar recursos** simplesmente incrementando o número (`/posts/1`, `/posts/2`, ...)
- Parece pouco profissional e expõe detalhes internos da aplicação

---

## A solução

O Rotabonita **intercepta o Laravel internamente** e substitui esses IDs por tokens únicos de 11 caracteres, no mesmo formato usado pelo YouTube para os seus vídeos:

```
https://meusite.com/posts/BYPWtH2qYos
https://meusite.com/posts/K9mXpL2rTnQ
https://meusite.com/users/w4RvNcJ8ZoM
```

E o melhor: **o teu código não muda absolutamente nada.**

---

## Como é que funciona por dentro

O package actua em três momentos distintos, todos transparentes para o developer:

### 1. Quando crias um registo

```php
$post = Post::create(['title' => 'Olá mundo']);
```

O Rotabonita intercepta o evento de criação do Eloquent e gera automaticamente um token único, gravando-o na coluna `public_id` da tabela:

```
INSERT INTO posts (title, public_id, created_at, ...)
VALUES ('Olá mundo', 'BYPWtH2qYos', ...)
```

### 2. Quando geras uma URL

```php
route('posts.show', $post)
```

O Rotabonita substitui o gerador de URLs do Laravel e intercepta os parâmetros antes de construir a URL. Em vez de usar o `id` do model, usa o `public_id`:

```
ANTES:  /posts/1            ← expõe o ID da base de dados
DEPOIS: /posts/BYPWtH2qYos  ← token opaco e seguro
```

### 3. Quando um utilizador acede à URL

```
GET /posts/BYPWtH2qYos
```

O Rotabonita substitui o sistema de route model binding do Laravel e resolve automaticamente o token para o model correcto:

```sql
SELECT * FROM posts WHERE public_id = 'BYPWtH2qYos' LIMIT 1
```

Se o token não existir → HTTP 404, igual ao comportamento padrão do Laravel.

---

## Requisitos

| Dependência | Versão     |
|-------------|------------|
| PHP         | ^8.1       |
| Laravel     | ^10.0 ou ^11.0 |

---

## Instalação

### Passo 1 — Instalar o package

```bash
composer require arnaldo-tomo/rotabonita
```

O Laravel detecta e regista o package automaticamente (via auto-discovery). Não precisas de adicionar nada ao `config/app.php`.

---

### Passo 2 — Adicionar a coluna `public_id` às tabelas

O Rotabonita precisa de uma coluna chamada `public_id` em cada tabela que queiras proteger. O package fornece um stub de migration pronto a usar.

**Publicar o stub:**

```bash
php artisan vendor:publish --tag=rotabonita-migrations
```

Isto cria um ficheiro em `database/migrations/`. Abre-o e **muda o nome da tabela** para a tua tabela:

```php
// Antes:
protected string $table = 'your_table_name';

// Depois (exemplo para posts):
protected string $table = 'posts';
```

**Executar a migration:**

```bash
php artisan migrate
```

Repete este processo para cada tabela que queiras proteger.

---

### Passo 3 — Terminado

Não há mais nada a fazer. O package está activo.

---

## Comparação: antes e depois

A seguir tens um exemplo completo de uma aplicação Laravel típica. **Repara que nenhuma linha de código muda.**

### Model

```php
// ANTES                              // DEPOIS (igual — nada muda)
class Post extends Model              class Post extends Model
{                                     {
    protected $fillable = [               protected $fillable = [
        'title',                              'title',
        'content',                            'content',
    ];                                    ];
}                                     }
```

### Routes

```php
// ANTES                              // DEPOIS (igual — nada muda)
Route::resource(                      Route::resource(
    'posts',                              'posts',
    PostController::class                 PostController::class
);                                    );
```

### Controller

```php
// ANTES                              // DEPOIS (igual — nada muda)
public function show(Post $post)      public function show(Post $post)
{                                     {
    return view('posts.show',             return view('posts.show',
        compact('post')                       compact('post')
    );                                    );
}                                     }
```

### Blade / geração de URLs

```php
// ANTES
route('posts.show', $post)
// → https://meusite.com/posts/1

// DEPOIS (mesmo código — resultado diferente)
route('posts.show', $post)
// → https://meusite.com/posts/BYPWtH2qYos  ✅
```

### Criação de registos

```php
// ANTES
$post = Post::create(['title' => 'Olá']);
// $post->id = 1

// DEPOIS (mesmo código — resultado enriquecido)
$post = Post::create(['title' => 'Olá']);
// $post->id = 1
// $post->public_id = 'BYPWtH2qYos'  ← adicionado automaticamente
```

---

## O token: formato e segurança

Os tokens gerados pelo Rotabonita têm as seguintes características:

| Propriedade | Valor |
|---|---|
| Comprimento | 11 caracteres |
| Alfabeto | `A-Z a-z 0-9 _ -` (64 símbolos) |
| Total de combinações | 64¹¹ ≈ **74 quintiliões** |
| Geração | `random_bytes()` — criptograficamente seguro |
| Unicidade | Verificada na base de dados antes de guardar |
| Formato | Igual aos IDs de vídeos do YouTube |

O token não contém nenhuma informação sobre o registo (não é sequencial, não revela a data de criação, não revela a quantidade de registos).

---

## Comportamento em detalhe

### O que acontece se a tabela não tiver `public_id`?

Nada. O Rotabonita verifica a existência da coluna antes de agir. Se não existir, o comportamento é 100% o padrão do Laravel. **O teu código existente não quebra.**

### O que acontece se eu passar um ID numérico na URL?

O Rotabonita resolve por ID numérico normalmente, como o Laravel faria por padrão:

```
GET /posts/1        → resolves pelo id = 1   (compatibilidade)
GET /posts/BYPWtH2qYos → resolves pelo public_id  (comportamento novo)
```

### O que acontece se o token não existir?

É lançada uma `ModelNotFoundException`, que o Laravel converte automaticamente em HTTP 404 — exactamente como aconteceria com um ID inválido.

### E as rotas com cache (`php artisan route:cache`)?

Totalmente compatível. O sistema de route binding é registado de forma compatível com a cache de rotas do Laravel.

---

## Backfill: preencher registos existentes

Se adicionaste a coluna `public_id` a uma tabela que já tem dados, preenche os registos existentes com este comando no Tinker:

```bash
php artisan tinker
```

```php
>>> $generator = app(\Rotabonita\TokenGenerator::class);
>>> App\Models\Post::whereNull('public_id')->each(function ($post) use ($generator) {
...     $post->public_id = $generator->generateUnique($post);
...     $post->saveQuietly(); // saveQuietly evita re-disparar eventos
... });
```

---

## Avançado: models em directórios não convencionais

Por padrão, o Rotabonita procura models em `app/Models/` e `app/`. Se os teus models estão noutro local, podes registá-los manualmente no teu `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php

public function register(): void
{
    $this->app->bind('rotabonita.models', fn() => [
        \App\Domain\Blog\Post::class,
        \App\Domain\Commerce\Product::class,
        \App\Domain\Users\Customer::class,
    ]);
}
```

---

## Avançado: comprimento do token personalizado

O comprimento padrão é 11 caracteres (igual ao YouTube). Para usar um comprimento diferente, substitui o `TokenGenerator` no container:

```php
// app/Providers/AppServiceProvider.php

use Rotabonita\TokenGenerator;

public function register(): void
{
    $this->app->singleton(TokenGenerator::class, function () {
        return new class extends TokenGenerator {
            public function generate(int $length = 16): string
            {
                return parent::generate($length); // tokens de 16 chars
            }
        };
    });
}
```

---

## Estrutura do package

```
rotabonita/
│
├── composer.json                          ← Metadados e auto-discovery
├── README.md                              ← Esta documentação
│
├── database/
│   └── migrations/
│       └── add_public_id_to_table.php.stub  ← Stub de migration publicável
│
└── src/
    ├── RotabonitaServiceProvider.php      ← Ponto de entrada. Regista tudo automaticamente.
    ├── TokenGenerator.php                 ← Gerador de tokens NanoID criptograficamente seguro.
    ├── RouteBindingOverride.php           ← Detecção de models + override do route binding.
    └── RotabonitaUrlGenerator.php         ← Override do UrlGenerator para geração de URLs.
```

### Como os ficheiros se ligam entre si

```
Instalação do package
        ↓
RotabonitaServiceProvider (auto-descoberto)
        ├── Regista TokenGenerator como singleton
        ├── Regista RouteBindingOverride como singleton
        ├── Substitui o UrlGenerator do Laravel → route() usa public_id
        ├── Regista ouvinte global Model::creating → gera token em cada criação
        └── Regista route bindings → resolve tokens em public_id nas rotas
```

---

## Publicar no Packagist

1. Faz push do repositório para o GitHub: `https://github.com/arnaldo-tomo/rotabonita`
2. Cria uma conta em [packagist.org](https://packagist.org) e vai a **Submit Package**
3. Cola o URL do repositório e clica **Check** → **Submit**
4. O package fica disponível como `arnaldo-tomo/rotabonita`

Para actualizações automáticas sempre que fizeres push, configura o webhook:
- GitHub → Settings → Webhooks → Add webhook
- URL: `https://packagist.org/api/github?username=arnaldo-tomo`
- Content type: `application/json`
- Eventos: `Just the push event`

---

## Compatibilidade

| Laravel | PHP   | Estado       |
|---------|-------|--------------|
| 10.x    | 8.1+  | ✅ Suportado |
| 11.x    | 8.2+  | ✅ Suportado |

---

## Licença

MIT © [Arnaldo Tomo](https://github.com/arnaldo-tomo)
