# RAG Knowledge Base - Development Makefile
# Run `make help` to see all available commands

.PHONY: help up down restart logs logs-backend logs-frontend logs-worker logs-beat \
        ps build clean dev-backend dev-frontend dev install install-backend install-frontend \
        migrate migrate-new db-shell lint format test shell-backend shell-frontend \
        infra infra-down

# Default target
.DEFAULT_GOAL := help

# Colors for help output
BLUE := \033[36m
RESET := \033[0m

##@ Docker Commands

up: ## Start all Docker services
	docker-compose up -d

down: ## Stop all Docker services
	docker-compose down

restart: ## Restart all Docker services
	docker-compose down && docker-compose up -d

build: ## Build/rebuild all Docker images
	docker-compose build

build-no-cache: ## Build all images without cache
	docker-compose build --no-cache

ps: ## Show running containers
	docker-compose ps

clean: ## Stop services and remove volumes (WARNING: deletes data)
	docker-compose down -v

##@ Logs

logs: ## Follow logs for all services
	docker-compose logs -f

logs-backend: ## Follow backend logs
	docker-compose logs -f backend

logs-frontend: ## Follow frontend logs
	docker-compose logs -f frontend

logs-worker: ## Follow Celery worker logs
	docker-compose logs -f celery-worker

logs-beat: ## Follow Celery beat scheduler logs
	docker-compose logs -f celery-beat

logs-db: ## Follow PostgreSQL logs
	docker-compose logs -f postgres

logs-redis: ## Follow Redis logs
	docker-compose logs -f redis

logs-qdrant: ## Follow Qdrant vector DB logs
	docker-compose logs -f qdrant

##@ Infrastructure Only (for local development)

infra: ## Start only infrastructure services (postgres, redis, qdrant)
	docker-compose up -d postgres redis qdrant

infra-down: ## Stop infrastructure services
	docker-compose stop postgres redis qdrant

##@ Local Development

dev: ## Run backend and frontend locally (requires infra running)
	@echo "Starting infrastructure..."
	$(MAKE) infra
	@echo "Run 'make dev-backend' and 'make dev-frontend' in separate terminals"

dev-backend: ## Run backend dev server locally (uses venv)
	cd backend && .venv/bin/uvicorn app.main:app --reload

dev-frontend: ## Run frontend dev server locally
	cd frontend && npm run dev

dev-worker: ## Run Celery worker locally (uses venv)
	cd backend && .venv/bin/celery -A app.tasks.celery_app worker --loglevel=info

dev-beat: ## Run Celery beat scheduler locally (uses venv)
	cd backend && .venv/bin/celery -A app.tasks.celery_app beat --loglevel=info

##@ Installation

install: install-backend install-frontend ## Install all dependencies

venv: ## Create Python virtual environment
	python3 -m venv backend/.venv
	@echo "Virtual environment created. Activate with: source backend/.venv/bin/activate"

install-backend: ## Install backend Python dependencies (uses venv)
	cd backend && .venv/bin/pip install -e ".[dev]"

install-frontend: ## Install frontend npm dependencies
	cd frontend && npm install

##@ Database

migrate: ## Run database migrations (uses venv)
	cd backend && .venv/bin/alembic upgrade head

migrate-new: ## Create a new migration (uses venv, usage: make migrate-new MSG="description")
	cd backend && .venv/bin/alembic revision --autogenerate -m "$(MSG)"

migrate-down: ## Rollback one migration (uses venv)
	cd backend && .venv/bin/alembic downgrade -1

migrate-history: ## Show migration history (uses venv)
	cd backend && .venv/bin/alembic history

db-shell: ## Open PostgreSQL shell
	docker-compose exec postgres psql -U $${POSTGRES_USER:-raguser} -d $${POSTGRES_DB:-ragdb}

db-reset: ## Reset database (WARNING: deletes all data)
	docker-compose down -v postgres
	docker-compose up -d postgres
	@echo "Waiting for postgres to be ready..."
	@sleep 5
	$(MAKE) migrate

##@ Code Quality

lint: lint-backend lint-frontend ## Run all linters

lint-backend: ## Run Python linters (uses venv)
	cd backend && .venv/bin/ruff check .

lint-frontend: ## Run ESLint on frontend
	cd frontend && npm run lint

format: ## Format Python code with black (uses venv)
	cd backend && .venv/bin/black .

format-check: ## Check Python formatting without changes (uses venv)
	cd backend && .venv/bin/black --check .

##@ Testing

test: ## Run all tests (uses venv)
	cd backend && .venv/bin/pytest

test-v: ## Run tests with verbose output (uses venv)
	cd backend && .venv/bin/pytest -v

test-cov: ## Run tests with coverage report (uses venv)
	cd backend && .venv/bin/pytest --cov=app --cov-report=term-missing

##@ Shell Access

shell-backend: ## Open shell in backend container
	docker-compose exec backend /bin/bash

shell-frontend: ## Open shell in frontend container
	docker-compose exec frontend /bin/sh

shell-worker: ## Open shell in Celery worker container
	docker-compose exec celery-worker /bin/bash

redis-cli: ## Open Redis CLI
	docker-compose exec redis redis-cli

##@ Help

help: ## Show this help message
	@awk 'BEGIN {FS = ":.*##"; printf "\n$(BLUE)Usage:$(RESET)\n  make $(BLUE)<target>$(RESET)\n"} \
		/^[a-zA-Z_-]+:.*?##/ { printf "  $(BLUE)%-18s$(RESET) %s\n", $$1, $$2 } \
		/^##@/ { printf "\n$(BLUE)%s$(RESET)\n", substr($$0, 5) }' $(MAKEFILE_LIST)
