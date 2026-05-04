.PHONY: help test coverage scan all up down bootstrap

-include .env
export

COMPOSE      = docker compose
COMPOSE_TEST = docker compose -f docker-compose.test.yml

## Exibe esta ajuda
help:
	@grep -E '^[a-zA-Z_-]+:.*?##' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

## ── Ambiente de desenvolvimento ──────────────────────────────────────────────

bootstrap: ## Setup completo do zero (env, build, deps, chaves e migrate)
	@if [ ! -f .env ]; then cp .env.example .env; fi
	$(COMPOSE) up -d --build
	$(COMPOSE) exec app-php composer install
	$(COMPOSE) exec app-php php artisan key:generate --force
	$(COMPOSE) exec app-php php artisan jwt:secret --force
	@i=0; until $(COMPOSE) exec app-php php artisan migrate --force; do \
		i=$$((i+1)); \
		if [ $$i -ge 20 ]; then \
			echo "Erro: banco indisponivel apos varias tentativas."; \
			exit 1; \
		fi; \
		echo "Aguardando banco iniciar..."; \
		sleep 3; \
	done

up: ## Sobe os containers de desenvolvimento (app, db, swagger, sonar)
	$(COMPOSE) up -d

down: ## Para e remove os containers de desenvolvimento
	$(COMPOSE) down

migrate:
	$(COMPOSE) exec app-php php artisan migrate --force

seed: ## Roda as seeders no ambiente de desenvolvimento
	$(COMPOSE) exec app-php php artisan db:seed --force

seed-dev: ## Roda apenas o DevDataSeeder (dados de desenvolvimento)
	$(COMPOSE) exec app-php php artisan db:seed --class=DevDataSeeder --force

migrate-seed: ## Roda as migrations e em seguida as seeders
	$(COMPOSE) exec app-php php artisan migrate --force
	$(COMPOSE) exec app-php php artisan db:seed --force

## ── Testes ───────────────────────────────────────────────────────────────────

test: ## Roda a suíte de testes sem gerar coverage (mais rápido)
	$(COMPOSE_TEST) run --rm app-test sh -c "\
		php artisan config:clear && \
		php artisan migrate --force && \
		vendor/bin/phpunit --no-coverage"
	$(COMPOSE_TEST) down

coverage: ## Roda os testes e gera coverage.xml (PCOV)
	$(COMPOSE_TEST) up --build --abort-on-container-exit app-test
	$(COMPOSE_TEST) down

## ── Qualidade de código ──────────────────────────────────────────────────────

lint: ## Formata o código com Laravel Pint
	$(COMPOSE) exec app-php ./vendor/bin/pint

## ── SonarQube ────────────────────────────────────────────────────────────────

scan: ## Roda o sonar-scanner (requer SONAR_TOKEN e SonarQube em pé)
	@if [ -z "$$SONAR_TOKEN" ]; then \
		echo "\033[31mErro: variável SONAR_TOKEN não definida.\033[0m"; \
		echo "  Exemplo: SONAR_TOKEN=sqa_xxx make scan"; \
		exit 1; \
	fi
	$(COMPOSE) run --rm sonar-scanner

## ── Atalhos combinados ───────────────────────────────────────────────────────

ci: coverage scan ## Gera coverage e em seguida roda o scanner (pipeline CI)

all: up coverage scan ## Sobe ambiente, gera coverage e roda o scanner
