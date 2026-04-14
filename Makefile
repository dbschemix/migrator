PHP_VERSION ?= 8.3
VERSION ?= $$(git rev-parse --verify HEAD)
USER = $$(id -u)
ARGS = $(filter-out $@,$(MAKECMDGOALS))
DOCKER_RUN = docker run --init -it --rm -u ${USER} -v "$$(pwd):/app" -w /app

# https://marmelab.com/blog/2016/02/29/auto-documented-makefile.html
.PHONY: help tests fix check example
.DEFAULT_GOAL := help

help: ## Display this help screen
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

composer: ## composer install
	$(DOCKER_RUN) \
		composer:latest \
		composer install --optimize-autoloader --ignore-platform-reqs

composer-up: ## composer update
	$(DOCKER_RUN) \
		composer:latest \
		composer update --no-cache --ignore-platform-reqs

composer-dump: ## composer dump-autoload
	$(DOCKER_RUN) \
		composer:latest \
		composer dump-autoload

cr: ## composer require
	$(DOCKER_RUN) \
		composer:latest \
		composer require $(ARGS) --ignore-platform-reqs

psalm: ## psalm
	$(DOCKER_RUN) \
		ghcr.io/kuaukutsu/php:${PHP_VERSION}-cli \
		./vendor/bin/psalm --php-version=${PHP_VERSION} --no-cache --show-info=true --force-jit

phpstan: ## phpstan
	$(DOCKER_RUN) \
		ghcr.io/kuaukutsu/php:${PHP_VERSION}-cli \
		./vendor/bin/phpstan analyse -c phpstan.neon

phpunit: ## phpunit
	$(DOCKER_RUN) \
		ghcr.io/kuaukutsu/php:${PHP_VERSION}-cli \
		./vendor/bin/phpunit

phpcs: ## php code snifferphp: detect violations of a defined coding standard
	$(DOCKER_RUN) \
		ghcr.io/kuaukutsu/php:${PHP_VERSION}-cli \
		./vendor/bin/phpcs

phpcbf: ## php code sniffer: automatically correct
	$(DOCKER_RUN) \
		ghcr.io/kuaukutsu/php:${PHP_VERSION}-cli \
		./vendor/bin/phpcbf

rector: ## rector
	$(DOCKER_RUN) \
		ghcr.io/kuaukutsu/php:${PHP_VERSION}-cli \
		./vendor/bin/rector

fix: phpcbf rector ## run fix tools

check: phpcs psalm phpstan ## run analysis tools

infection:
	docker build \
		--build-arg PHP_VERSION=$(PHP_VERSION) \
		--build-arg USER=$(USER) \
		--build-arg WORKDIR=/app \
		--target tests \
		-t app_cli .docker/php/cli
	- docker run --init -it --rm \
		--add-host=host.docker.internal:host-gateway \
		-u $(USER) \
		-v "$$(pwd):/app" \
		-w /app \
		app_cli ./vendor/bin/infection
	docker image rm -f app_cli

tests:
	docker build \
		--build-arg PHP_VERSION=$(PHP_VERSION) \
		--build-arg USER=$(USER) \
		--build-arg WORKDIR=/app \
		--target tests \
		-t app_cli .docker/php/cli
	- docker run --init -it --rm \
		--add-host=host.docker.internal:host-gateway \
		-u $(USER) \
		-v "$$(pwd):/app" \
		-w /app \
		app_cli ./vendor/bin/phpunit \
			--configuration phpunit.xml.dist \
			--coverage-xml=/app/runtime/coverage/coverage-xml \
			--coverage-clover=/app/runtime/coverage/clover.xml \
			--log-junit=/app/runtime/coverage/phpunit.junit.xml
	- docker run --init -it --rm \
		--add-host=host.docker.internal:host-gateway \
		-u $(USER) \
		-v "$$(pwd):/app" \
		-w /app \
		app_cli ./vendor/bin/infection \
			--coverage=/app/runtime/coverage \
			--threads=max \
			--skip-initial-tests
	docker image rm -f app_cli

## Application

cli:
	$(DOCKER_RUN) -w /app/example \
 		ghcr.io/kuaukutsu/php:${PHP_VERSION}-cli \
 		sh -l

example:
	VERSION=$(VERSION) USER=$(USER) \
		docker compose run --rm -u $(USER) -w /example app sh -l
	make stop

stop: ## Stop server
	docker compose -f ./docker-compose.yml stop

down: stop
	docker compose -f ./docker-compose.yml down --remove-orphans

remove: down _image_remove _container_remove _volume_remove

_image_remove:
	docker image rm -f \
		migrator-app \
		migrator-postgres \
		migrator-mysql

_container_remove:
	docker rm -f \
		migrator_postgres \
		migrator_mysql

_volume_remove:
	docker volume rm -f \
		migrator_pg_data \
		migrator_mysql_data
