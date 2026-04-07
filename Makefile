
.PHONY: up down build shell drush install cr test help

# Default target
help:
	@echo "Usage: make [target]"
	@echo ""
	@echo "Targets:"
	@echo "  up        Start the project containers in the background"
	@echo "  down      Stop and remove the project containers"
	@echo "  build     Build and start the project containers"
	@echo "  shell     Enter the drupal container shell"
	@echo "  drush     Run a drush command (e.g., make drush cmd=status)"
	@echo "  install   Install composer dependencies inside the container"
	@echo "  cr        Clear Drupal cache"
	@echo "  test      Run PHPUnit tests"

up:
	docker-compose up -d

down:
	docker-compose down

build:
	docker-compose up -d --build

shell:
	docker-compose exec drupal bash

drush:
	docker-compose exec drupal vendor/bin/drush $(cmd)

install:
	docker-compose exec drupal composer install

cr:
	docker-compose exec drupal vendor/bin/drush cr

test:
	docker-compose exec drupal ./vendor/bin/phpunit tests/
