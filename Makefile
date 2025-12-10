# Makefile for money-transfer-monorepo
# -------------------------------
# Put a .env file in the repo root with defaults, e.g.:
# RABBIT_HOST=rabbit
# RABBIT_PORT=5672
# RABBIT_USER=guest
# RABBIT_PASS=guest
# RABBIT_VHOST=%2f
# RABBIT_DSN=amqp://guest:guest@rabbit:5672/%2f
# REDIS_URL=redis://redis:6379
# USERS_DB_URL=mysql://user:pass@mysql:3306/users_db
# ACCOUNTS_DB_URL=mysql://acc:pass@mysql:3306/accounts_db
#
# The Makefile will load and EXPORT the variables declared in .env
# so they are available to docker run and child processes.

SHELL := /bin/bash
.PHONY: infra-up infra-down build-images up down \
        start-users-local stop-users-local logs-users exec-users migrate-users \
        start-accounts-local stop-accounts-local logs-accounts exec-accounts \
        build-users-image build-accounts-image demo clean

ROOT_DIR := $(shell pwd)
USERS_DIR := $(ROOT_DIR)/services/users-service
ACCOUNTS_DIR := $(ROOT_DIR)/services/accounts-service

# Default container names for local dev runs
USERS_CONTAINER := money-users-local
ACCOUNTS_CONTAINER := money-accounts-local

# Default host ports for local dev
USERS_PORT ?= 8001
ACCOUNTS_PORT ?= 8002

# Composer image (used to install vendor into bind-mounted volume)
COMPOSER_IMAGE := composer:2

# Docker network used to connect infra and service containers. If not present, make will create it.
NETWORK ?= money-net

# ---- Load and export variables from .env (if present) ----
ifneq (,$(wildcard .env))
  include .env
  # Extract variable names from .env (simple, ignores comments and empty lines)
  ENV_VARS := $(shell sed -n 's/^\([A-Za-z_][A-Za-z0-9_]*\)=.*$$/\1/p' .env)
  export $(ENV_VARS)
else
  $(warning .env file not found in repo root. Create one from .env.example or export variables manually.)
endif

# Provide safe defaults (only if not set in .env)
RABBIT_DSN ?= amqp://guest:guest@rabbit:5672/%2f
REDIS_URL ?= redis://redis:6379
USERS_DB_URL ?= mysql://user:pass@mysql:3306/users_db
ACCOUNTS_DB_URL ?= mysql://acc:pass@mysql:3306/accounts_db

# -------------------------
# docker-compose infra
# -------------------------
infra-up:
	@echo "Creating docker network '$(NETWORK)' if it does not exist..."
	@if [ -z "$$(docker network ls --filter name=^$(NETWORK)$$ --format '{{.Name}}')" ]; then docker network create $(NETWORK); else echo "network $(NETWORK) exists"; fi
	@echo "Starting infra (rabbit, redis, mysql, mysql) via docker-compose..."
	docker-compose up -d rabbit redis mysql mysql
	@echo "Waiting for some infra to be ready (this is a basic probe)..."
	# basic wait loop for rabbit management port
	until curl -sS http://localhost:15672 >/dev/null 2>&1; do echo "waiting for rabbitmq (15672) ..."; sleep 1; done
	@echo "Infra containers started."

infra-down:
	@echo "Stopping infra services via docker-compose..."
	docker-compose stop rabbit redis mysql mysql || true
	docker-compose rm -f rabbit redis mysql mysql || true
	@echo "Infra stopped (containers removed)."

# -------------------------
# full stack via docker-compose (not recommended for iterative local dev)
# -------------------------
up:
	@echo "Bringing full stack up via docker-compose (services + infra)..."
	docker-compose up -d
	@echo "Done."

down:
	docker-compose down --volumes --remove-orphans

# -------------------------
# build images (optional)
# -------------------------
build-images: build-users-image build-accounts-image

build-users-image:
	@echo "Building users-service image..."
	docker build -t money/users-service:dev $(USERS_DIR)

build-accounts-image:
	@echo "Building accounts-service image..."
	docker build -t money/accounts-service:dev $(ACCOUNTS_DIR)

# -------------------------
# users-service local (bind-mounted) runner
# -------------------------
start-users-local: infra-up
	@echo "Preparing to start users-service (local bind-mounted container)..."
	@echo "Installing PHP dependencies inside a temporary composer container (will write into $(USERS_DIR)/vendor)..."
	docker run --rm -u $(shell id -u):$(shell id -g) -v $(USERS_DIR):/app -w /app $(COMPOSER_IMAGE) install --ignore-platform-reqs --no-interaction
	@echo "Ensuring docker network '$(NETWORK)' exists..."
	@if [ -z "$$(docker network ls --filter name=^$(NETWORK)$$ --format '{{.Name}}')" ]; then docker network create $(NETWORK); fi
	@echo "Starting users-service container as $(USERS_CONTAINER) on port $(USERS_PORT)..."
	-docker rm -f $(USERS_CONTAINER) || true
	docker run -d --name $(USERS_CONTAINER) \
	  --network $(NETWORK) \
	  -p $(USERS_PORT):8000 \
	  -v $(USERS_DIR):/srv/app \
	  -w /srv/app \
	  -e DATABASE_URL="$(USERS_DB_URL)" \
	  -e MESSENGER_TRANSPORT_DSN="$(RABBIT_DSN)" \
	  -e REDIS_URL="$(REDIS_URL)" \
	  -e APP_ENV=dev \
	  money/users-service:dev \
	  sh -c "composer dump-autoload --ansi && php -S 0.0.0.0:8000 -t public"
	@echo "users-service started and available at http://localhost:$(USERS_PORT)"

stop-users-local:
	@echo "Stopping users-service container..."
	docker rm -f $(USERS_CONTAINER) || true
	@echo "Stopped."

logs-users:
	@echo "Tailing logs from $(USERS_CONTAINER)... (Ctrl-C to exit)"
	docker logs -f $(USERS_CONTAINER)

exec-users:
	@if [ -z "$$(docker ps -q -f name=$(USERS_CONTAINER))" ]; then echo "users container not running"; else docker exec -it $(USERS_CONTAINER) /bin/bash; fi

migrate-users:
	@if [ -z "$$(docker ps -q -f name=$(USERS_CONTAINER))" ]; then echo "users container not running. Use 'make start-users-local' first."; exit 1; fi
	@echo "Running users-service migrations..."
	docker exec -it $(USERS_CONTAINER) sh -c "php bin/console doctrine:migrations:migrate --no-interaction"

# -------------------------
# accounts-service local (bind-mounted) runner
# -------------------------
start-accounts-local: infra-up
	@echo "Preparing to start accounts-service (local bind-mounted container)..."
	@echo "Installing PHP dependencies inside a temporary composer container (will write into $(ACCOUNTS_DIR)/vendor)..."
	docker run --rm -u $(shell id -u):$(shell id -g) -v $(ACCOUNTS_DIR):/app -w /app $(COMPOSER_IMAGE) install --ignore-platform-reqs --no-interaction
	@echo "Ensuring docker network '$(NETWORK)' exists..."
	@if [ -z "$$(docker network ls --filter name=^$(NETWORK)$$ --format '{{.Name}}')" ]; then docker network create $(NETWORK); fi
	@echo "Starting accounts-service container as $(ACCOUNTS_CONTAINER) on port $(ACCOUNTS_PORT)..."
	-docker rm -f $(ACCOUNTS_CONTAINER) || true
	docker run -d --name $(ACCOUNTS_CONTAINER) \
	  --network $(NETWORK) \
	  -p $(ACCOUNTS_PORT):8000 \
	  -v $(ACCOUNTS_DIR):/srv/app \
	  -w /srv/app \
	  -e DATABASE_URL="$(ACCOUNTS_DB_URL)" \
	  -e MESSENGER_TRANSPORT_DSN="$(RABBIT_DSN)" \
	  -e REDIS_URL="$(REDIS_URL)" \
	  -e JWT_PUBLIC_KEY_PATH="/srv/app/config/jwt/public.pem" \
	  -e APP_ENV=dev \
	  money/accounts-service:dev \
	  sh -c "composer dump-autoload --ansi && php -S 0.0.0.0:8000 -t public"
	@echo "accounts-service started and available at http://localhost:$(ACCOUNTS_PORT)"

stop-accounts-local:
	@echo "Stopping accounts-service container..."
	docker rm -f $(ACCOUNTS_CONTAINER) || true
	@echo "Stopped."

logs-accounts:
	@echo "Tailing logs from $(ACCOUNTS_CONTAINER)... (Ctrl-C to exit)"
	docker logs -f $(ACCOUNTS_CONTAINER)

exec-accounts:
	@if [ -z "$$(docker ps -q -f name=$(ACCOUNTS_CONTAINER))" ]; then echo "accounts container not running"; else docker exec -it $(ACCOUNTS_CONTAINER) /bin/bash; fi

migrate-accounts:
	@if [ -z "$$(docker ps -q -f name=$(ACCOUNTS_CONTAINER))" ]; then echo "accounts container not running. Use 'make start-accounts-local' first."; exit 1; fi
	@echo "Running accounts-service migrations..."
	docker exec -it $(ACCOUNTS_CONTAINER) sh -c "php bin/console doctrine:migrations:migrate --no-interaction"

# -------------------------
# convenience / demo
# -------------------------
demo:
	@echo "Demo placeholder: run scripts/demo.sh or implement this target to call your demo script."
	@if [ -f ./scripts/demo.sh ]; then bash ./scripts/demo.sh; else echo "Create scripts/demo.sh to automate demo."; fi

# -------------------------
# cleanup
# -------------------------
clean:
	@echo "Stopping and removing local service containers (if any)..."
	-docker rm -f $(USERS_CONTAINER) $(ACCOUNTS_CONTAINER) || true
	@echo "Removing built images (optional)..."
	-docker rmi money/users-service:dev money/accounts-service:dev || true
	@echo "Done."
