<p align="center">
  <img src=".github/banner.png" alt="Rotabonita" width="100%">
</p>

# Rotabonita

> Instala o package. É tudo. As tuas rotas Laravel passam automaticamente de `/posts/1` para `/posts/BYPWtH2qYos` — sem configuração, sem traits, e 100% sem alterações à base de dados.

**Rotabonita** é um package Laravel 10/11 que substitui automaticamente os IDs numéricos da base de dados nas URLs por tokens curtos, seguros e legíveis — o mesmo formato de 11 caracteres que o YouTube usa nos seus vídeos (ex: `BYPWtH2qYos`).

Funciona dinamicamente em memória:
- **Zero Colunas na Base de Dados** — Não necessita de criar ou adicionar colunas `public_id` às tabelas.
- **Zero Migrações** — Nada de Artisan commands nem de schemas. É instantâneo.
- **Geração de URL** — `route('posts.show', $post)` produz logicamente `/posts/BYPWtH2qYos` convertendo em tempo-real.
- **Resolução de rota** — Converte `/posts/BYPWtH2qYos` invisivelmente de volta para `/posts/1` antes da query Eloquent iniciar.

**Sem traits. Sem modificar models. Sem publicar configurações. Sem alterar rotas. Só instalar.**

[![Latest Stable Version](https://poser.pugx.org/arnaldo-tomo/rotabonita/v/stable)](https://packagist.org/packages/arnaldo-tomo/rotabonita)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.1-8892BF)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-10%20%7C%2011-FF2D20)](https://laravel.com)

> 📖 [English Documentation](README.md)

---

## Instalação

```bash
composer require arnaldo-tomo/rotabonita
```

**Terminado. Nenhuma configuração adicional, nem comandos publicar necessários.**

A tua aplicação Laravel utilizará agora magicamente tokens estilo-YouTube para todos os Models baseados em inteiros incrementais de imediato.

---

## O que muda

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

O teu código fica **exactamente igual**. Models, rotas, controllers, templates Blade — nada muda.

---

## Como funciona

Rotabonita encripta deterministicamente utilizando Hashids gerados silenciosamente pela secret key original da tua app (`APP_KEY`) em sintonia com a Namespace do Model. Isto garante que:
1. Trabalha de forma ultrarrápida (O(1)) através da memória — poupando as habituais queries ou lookups secundários.
2. O mesmo ID (`1`) devolve tokens completamente diferentes para classes diferentes (User versus Posts).
3. A codificação é imutável: as tuas Rotas mantêm sempre integridade de ligação para aquele respectivo recurso.

O package intercepta o Laravel no Kernel:

**1 — Na geração de URLs**
```php
route('posts.show', $post); // Substitui a URL Generator Core silenciosamente (exibe BYPWtH2qYos)
```

**2 — Na resolução da rota (antes da Invocação das Actions)**
```
Reverte: BYPWtH2qYos → 1
GET /posts/BYPWtH2qYos → Resolve exactamente como se usasse /posts/1 nativamente.
```

Se o acesso for falhado (URL inválida ou Model numérico entretanto indisponível) → HTTP 404 padrão Laravel.

---

## O teu código não muda

```php
// Model — igual (Olha! Sem Traits!)
class Post extends Model
{
    protected $fillable = ['title'];
}

// Rota — igual
Route::resource('posts', PostController::class);

// Controller — igual
public function show(Post $post): View
{
    return view('posts.show', compact('post'));
}

// Blade — igual, mas agora gera a URL com o respetivo token encriptado nativamente
route('posts.show', $post) // → /posts/BYPWtH2qYos ✅
```

---

## Formato do token

| Propriedade | Valor |
|---|---|
| Algoritmo | Fully Dynamic Hashids |
| Comprimento | 11 caracteres |
| Alfabeto | `A–Z a–z 0–9 _ -` (64 símbolos) |
| Proteção | Transforma de forma opaca com recurso a Cryptografia |
| Segurança Modelos | Mantém flexibilidade: Ignora silênciosamente UUIDs ou Custom String Keys |

---

## Porquê usar o Rotabonita?

Por padrão, o Laravel expõe os IDs da base de dados directamente nas URLs:
Isto é problemático porque:
- Qualquer utilizador consegue saber **quantos registos** existem
- É fácil **enumerar recursos** incrementando o número
- A aplicação parece menos madura e mais exposta.

O Rotabonita resolve isto em literalmente segundos através da directiva require e sem complexidade de arquitectura adjacente para um Developer.

---

## Compatibilidade

| Laravel | PHP  | Estado |
|---------|------|--------|
| 10.x    | 8.1+ | ✅ Suportado |
| 11.x    | 8.2+ | ✅ Suportado |

---

## Licença

MIT © [Arnaldo Tomo](https://github.com/arnaldo-tomo)
