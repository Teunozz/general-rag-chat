# general-purpose-rag Development Guidelines

## Active Technologies
- PHP 8.4+ / Laravel 12, PostgreSQL 17 + pgvector, Redis (queue/cache)
- Key packages: `laravel/ai`, `pgvector/pgvector`, `roach-php/core`, `spatie/laravel-ciphersweet`, `fivefilters/readability.php`, `laminas/laminas-feed`
- Frontend: Tailwind CSS + Alpine.js (CSP build via `@alpinejs/csp`)
- Testing: Pest (NOT PHPUnit) — use `test()` / `it()` syntax, no class-based tests

## Project Structure

```text
app/          # Laravel application code (Models, Services, Jobs, Http, Ai, Spiders)
database/     # Migrations, factories, seeders
resources/    # Blade views, CSS (Tailwind), JS (Alpine.js)
tests/        # Pest tests (Feature + Unit)
```

## Commands — Use `make` targets (preferred over raw docker commands)

```bash
make test                    # Full test suite (Pest)
make test-filter f="MyTest"  # Filter tests by name
make pint                    # Fix code style (PSR-12)
make pint-check              # Check style without fixing
make phpstan                 # Static analysis (Larastan level 5)
make rector                  # Rector dry-run (code quality)
make rector-fix              # Apply Rector fixes
make lint                    # Run both pint-check + phpstan
make migrate                 # Run migrations
make ide-helper              # Regenerate IDE helper files
make fresh                   # Full rebuild: down, rebuild, migrate:fresh --seed, build
```

## Development Workflow

1. **Before writing code**: Read existing files to understand context
2. **Writing tests**: Use Pest syntax (`test('...', function () { ... })`), run with `make test`
3. **After writing code**: Run `make pint` to fix style, then `make phpstan` for static analysis
4. **Refactoring**: Run `make rector` (dry-run first) to check for automated improvements
5. **After model changes**: Run `make ide-helper` to regenerate PHPDoc stubs

## Code Style

- PSR-12 enforced by Laravel Pint (`pint.json`: preset `psr12`)
- Larastan level 5 (`phpstan.neon`): all code in `app/` must pass
- Rector (`rector.php`): PHP 8.4 sets + deadCode, codeQuality, typeDeclarations, earlyReturn
- Eloquent ORM for all DB access. Laravel conventions for auth, validation, middleware, policies.
- Tests use Pest: `test()` closures, `$this->` for HTTP helpers, no `extends TestCase` in test files
