-include .env

MAKEFLAGS += --silent

COMPOSE     = docker compose
EXEC        = $(COMPOSE) exec app
EXEC_T      = $(COMPOSE) exec -T app
ARTISAN     = $(EXEC) php artisan
ARTISAN_T   = $(EXEC_T) php artisan
DOCKER      = docker

.PHONY: help up down stop restart rebuild ps logs logs-app logs-worker \
        shell tinker artisan \
        migrate migrate-fresh migrate-rollback seed \
        test test-unit test-feature test-filter \
        pint pint-check phpstan rector lint \
        build dev \
        vendor vendor-update \
        queue schedule \
        create-admin refresh-feeds recap \
        ide-helper cache-clear setup fresh

# ─── Help ────────────────────────────────────────────────────────────────────

help: ## Show this help
	@printf "\n\033[1mUsage:\033[0m make [target]\n\n"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' Makefile | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ""

default: help

# ─── Docker ──────────────────────────────────────────────────────────────────

up: ## Start all containers
	$(COMPOSE) up -d

down: ## Stop and remove containers
	$(COMPOSE) down --remove-orphans

stop: ## Stop containers (keep state)
	$(COMPOSE) stop

restart: ## Restart all containers
	$(COMPOSE) restart

rebuild: ## Rebuild images and start containers
	$(COMPOSE) up -d --build

ps: ## Show running containers
	$(COMPOSE) ps

logs: ## Tail all container logs
	$(COMPOSE) logs -f --tail=50

logs-app: ## Tail app container logs
	$(COMPOSE) logs -f --tail=50 app

logs-worker: ## Tail worker container logs
	$(COMPOSE) logs -f --tail=50 worker

# ─── Shell & REPL ───────────────────────────────────────────────────────────

shell: ## Open a bash shell in the app container
	$(EXEC) bash

tinker: ## Open Laravel Tinker REPL
	$(ARTISAN) tinker

artisan: ## Run an artisan command (usage: make artisan cmd="migrate:status")
	$(ARTISAN) $(cmd)

# ─── Database ────────────────────────────────────────────────────────────────

migrate: ## Run database migrations
	$(ARTISAN_T) migrate

migrate-fresh: ## Drop all tables and re-run migrations with seeders
	$(ARTISAN_T) migrate:fresh --seed

migrate-rollback: ## Rollback the last migration batch
	$(ARTISAN_T) migrate:rollback

seed: ## Run database seeders
	$(ARTISAN_T) db:seed

# ─── Testing ─────────────────────────────────────────────────────────────────

test: ## Run the full test suite
	$(ARTISAN_T) config:clear
	$(EXEC_T) php artisan test

test-unit: ## Run only unit tests
	$(EXEC_T) php artisan test --testsuite=Unit

test-feature: ## Run only feature tests
	$(EXEC_T) php artisan test --testsuite=Feature

test-filter: ## Run tests matching a filter (usage: make test-filter f="UserTest")
	$(EXEC_T) php artisan test --filter=$(f)

# ─── Code Quality ────────────────────────────────────────────────────────────

pint: ## Fix code style with Laravel Pint
	$(EXEC_T) ./vendor/bin/pint

pint-check: ## Check code style without fixing
	$(EXEC_T) ./vendor/bin/pint --test

phpstan: ## Run static analysis with Larastan
	$(EXEC_T) ./vendor/bin/phpstan analyse --memory-limit=2G

rector: ## Run Rector refactoring (dry-run)
	$(EXEC_T) ./vendor/bin/rector --dry-run

rector-fix: ## Apply Rector refactoring
	$(EXEC_T) ./vendor/bin/rector

lint: pint-check phpstan ## Run all code quality checks

# ─── Frontend ────────────────────────────────────────────────────────────────

build: ## Build frontend assets for production
	npm run build

dev: ## Start Vite dev server
	npm run dev

# ─── Dependencies ────────────────────────────────────────────────────────────

vendor: ## Install and sync vendor directory from container to host
	$(EXEC) bash -c 'composer install && find vendor -type l -exec /bin/rm {} \;'
	$(DOCKER) cp general-purpose-rag-app-1:/var/www/html/vendor ./
	$(EXEC) bash -c 'rm -rf vendor/* && composer install'

vendor-update: ## Update composer dependencies and sync to host
	$(EXEC) bash -c 'composer update --lock && composer install && find vendor -type l -exec /bin/rm {} \;'
	$(DOCKER) cp general-purpose-rag-app-1:/var/www/html/vendor ./
	$(EXEC) bash -c 'rm -rf vendor/* && composer install && composer update --lock'

# ─── Queue & Scheduler ──────────────────────────────────────────────────────

queue: ## Start a queue worker (foreground)
	$(ARTISAN) queue:work --sleep=3 --tries=3 --max-time=3600

queue-restart: ## Restart queue workers after code change
	$(ARTISAN_T) queue:restart

schedule: ## Run the scheduler once
	$(ARTISAN_T) schedule:run

# ─── Application Commands ───────────────────────────────────────────────────

create-admin: ## Create an admin user
	$(ARTISAN) app:create-admin

refresh-feeds: ## Refresh all RSS feeds
	$(ARTISAN_T) app:refresh-feeds

recap: ## Generate recap emails (usage: make recap t="daily")
	$(ARTISAN_T) app:generate-recap $(t)

# ─── IDE Helper ──────────────────────────────────────────────────────────────

ide-helper: ## Generate IDE helper files (PHPDoc + models + meta)
	$(ARTISAN_T) ide-helper:generate
	$(ARTISAN_T) ide-helper:models --nowrite
	$(ARTISAN_T) ide-helper:meta

# ─── Utilities ───────────────────────────────────────────────────────────────

cache-clear: ## Clear all Laravel caches
	$(ARTISAN_T) config:clear
	$(ARTISAN_T) cache:clear
	$(ARTISAN_T) route:clear
	$(ARTISAN_T) view:clear

setup: up migrate build ## First-time setup: start containers, migrate, build assets

fresh: down rebuild migrate-fresh build ## Full rebuild: teardown, rebuild, migrate, seed, build assets
