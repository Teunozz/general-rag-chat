# general-purpose-rag Development Guidelines

Auto-generated from all feature plans. Last updated: 2026-02-06

## Active Technologies
- PostgreSQL 17 with pgvector extension, Redis (queue/cache) (001-knowledge-base-rag)

- PHP 8.3+ / Laravel 12 + Laravel AI SDK (`laravel/ai`), pgvector (`pgvector/pgvector`), Roach PHP (`roach-php/core`, `roach-php/laravel`), CipherSweet (`spatie/laravel-ciphersweet`), Readability (`fivefilters/readability.php`), Laminas Feed (`laminas/laminas-feed`) (001-knowledge-base-rag)

## Project Structure

```text
app/          # Laravel application code (Models, Services, Jobs, Http, Ai, Spiders)
database/     # Migrations, factories, seeders
resources/    # Blade views, CSS (Tailwind), JS (Alpine.js)
tests/        # Feature and Unit tests
```

## Commands

```bash
docker compose exec app php artisan test          # Run tests
docker compose exec app ./vendor/bin/pint          # Code style (PSR-12)
docker compose exec app php artisan migrate        # Run migrations
docker compose exec app php artisan queue:work     # Process jobs
```

## Code Style

PHP 8.3+ / Laravel 12: PSR-12 enforced by Laravel Pint. Eloquent ORM for all DB access. Laravel conventions for auth, validation, middleware, policies.

## Recent Changes
- 001-knowledge-base-rag: Added PHP 8.3+ / Laravel 12 + Laravel AI SDK (`laravel/ai`), pgvector (`pgvector/pgvector`), Roach PHP (`roach-php/core`, `roach-php/laravel`), CipherSweet (`spatie/laravel-ciphersweet`), Readability (`fivefilters/readability.php`), Laminas Feed (`laminas/laminas-feed`)

<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
