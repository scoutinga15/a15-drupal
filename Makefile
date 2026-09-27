.PHONY: help up down build shell drush cr updb cex cim translations test \
	upgrade db-backup db-restore \
	live-status live-backup live-files-backup live-cex live-db-pull release deploy-dry-run deploy

# Local environment
DC     := docker compose -f docker-compose.yml -f docker-compose.override.yml
DRUSH  := $(DC) exec -T drupal vendor/bin/drush

# Live environment (override on the command line, e.g. make deploy LIVE_HOST=user@host)
LIVE_HOST ?= root@94.130.98.128
LIVE_DIR  ?= /root/projects/a15-drupal
LIVE_DC   ?= docker compose -f docker-compose.yml -f docker-compose.override.yml
LIVE_CTR  ?= a15-drupal-drupal-1
LIVE_SSH  := ssh $(LIVE_HOST)
LIVE_DRUSH := $(LIVE_SSH) docker exec $(LIVE_CTR) vendor/bin/drush

# Git ref (branch, tag or commit) to deploy
REF ?= master

# The commit is exported with git archive and rsynced into LIVE_DIR. Stale files
# are only deleted in directories git fully owns; .env and data are never touched.
RELEASE_DIR   := backups/.release
MANAGED_DIRS  := config patches web/modules/custom web/themes/custom
RSYNC_EXCLUDE := --exclude=/.env --exclude=/local.env \
	--exclude=/backups/ --exclude='/web/sites/*/files/' --exclude='/web/sites/*/settings.local.php'

BACKUP_DIR := backups
STAMP      := $(shell date +%Y%m%d-%H%M%S)
SYNC_DIR   := config/sync

help: ## Show this help
	@grep -hE '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

## --- Local -------------------------------------------------------------------

up: ## Start the local containers
	$(DC) up -d

down: ## Stop and remove the local containers
	$(DC) down

build: ## Build and start the local containers
	$(DC) up -d --build

shell: ## Open a shell in the local drupal container
	$(DC) exec drupal bash

drush: ## Run a drush command locally (make drush cmd="status")
	$(DRUSH) $(cmd)

cr: ## Clear the local Drupal cache
	$(DRUSH) cr

updb: ## Run pending database updates locally
	$(DRUSH) updb -y

cex: ## Export local configuration to config/sync
	$(DRUSH) cex -y

cim: ## Import config/sync into the local site
	$(DRUSH) cim -y

translations: ## Import the Dutch .po files of the custom modules (keeps admin-edited translations)
	@for f in web/modules/custom/*/translations/*.nl.po; do [ -f "$$f" ] || continue; \
		echo "Importing $$f"; \
		$(DRUSH) locale:import nl "/opt/drupal/$$f" --type=not-customized --override=not-customized || exit 1; \
	done
	$(DRUSH) cr

test: ## Run PHPUnit tests
	$(DC) exec drupal ./vendor/bin/phpunit tests/

upgrade: ## Update composer packages (make upgrade pkg="drupal/core-*"), rebuild, run updates and export config
	docker run --rm -v "$(CURDIR)":/app -w /app composer:2 update $(pkg) -W --no-install --ignore-platform-req='ext-*'
	$(DC) up -d --build
	$(DRUSH) updb -y
	$(DRUSH) cex -y
	@echo "Review 'git diff' (composer.lock and config/sync) before committing."

db-backup: ## Dump the local database to backups/
	@mkdir -p $(BACKUP_DIR)
	$(DRUSH) sql-dump --gzip > $(BACKUP_DIR)/local-db-$(STAMP).sql.gz
	gunzip -t $(BACKUP_DIR)/local-db-$(STAMP).sql.gz
	@echo "Saved $(BACKUP_DIR)/local-db-$(STAMP).sql.gz"

db-restore: ## Replace the local database with a dump (make db-restore FILE=backups/x.sql.gz)
	@test -f "$(FILE)" || { echo "Usage: make db-restore FILE=backups/<dump>.sql.gz"; exit 1; }
	$(DRUSH) sql-drop -y
	gunzip -c "$(FILE)" | $(DRUSH) sql-cli
	@# No cache rebuild: with code of another Drupal version it breaks the restored site.
	@echo "Restored $(FILE). Run 'make cr' if the dump comes from the same code version."

## --- Live --------------------------------------------------------------------

live-status: ## Show the deployed commit and Drupal status on live
	@$(LIVE_SSH) 'cat $(LIVE_DIR)/REVISION 2>/dev/null || echo "No REVISION file: not deployed with make deploy yet"'
	@$(LIVE_SSH) 'docker exec $(LIVE_CTR) php -r "echo \"PHP version      : \", PHP_VERSION, PHP_EOL;"'
	$(LIVE_DRUSH) status --fields=drupal-version,drush-version,db-status,bootstrap
	$(LIVE_DRUSH) updatedb:status
	$(LIVE_DRUSH) config:status

live-backup: ## Dump the live database to backups/
	@mkdir -p $(BACKUP_DIR)
	$(LIVE_DRUSH) sql-dump --gzip > $(BACKUP_DIR)/live-db-$(STAMP).sql.gz
	gunzip -t $(BACKUP_DIR)/live-db-$(STAMP).sql.gz
	@echo "Saved $(BACKUP_DIR)/live-db-$(STAMP).sql.gz"

live-files-backup: ## Download the live public files to backups/
	@mkdir -p $(BACKUP_DIR)
	$(LIVE_SSH) 'docker exec $(LIVE_CTR) tar -C /opt/drupal/web/sites/default/files --exclude=./php --exclude=./css --exclude=./js --exclude=./styles -cz .' > $(BACKUP_DIR)/live-files-$(STAMP).tgz
	gzip -t $(BACKUP_DIR)/live-files-$(STAMP).tgz
	@echo "Saved $(BACKUP_DIR)/live-files-$(STAMP).tgz"

live-cex: ## Export live configuration into config/sync (review with git diff)
	@rm -rf $(BACKUP_DIR)/.cex-live && mkdir -p $(BACKUP_DIR)/.cex-live
	$(LIVE_SSH) 'docker exec $(LIVE_CTR) sh -c "rm -rf /tmp/cex-live && vendor/bin/drush config:export --destination=/tmp/cex-live -y >/dev/null && tar -C /tmp/cex-live -cz . && rm -rf /tmp/cex-live"' | tar -xz -C $(BACKUP_DIR)/.cex-live
	@# Webforms are excluded by config_ignore, and passwords stay out of git.
	rsync -a --delete --exclude='.htaccess' --exclude='webform.webform.*' $(BACKUP_DIR)/.cex-live/ $(SYNC_DIR)/
	@sed -i.bak -E "s/^(    password: ).+/\1''/" $(SYNC_DIR)/swiftmailer.transport.yml 2>/dev/null; rm -f $(SYNC_DIR)/*.bak
	@rm -rf $(BACKUP_DIR)/.cex-live
	@git status --short $(SYNC_DIR)

live-db-pull: live-backup ## Replace the local database with a fresh copy of live
	$(MAKE) db-restore FILE=$(BACKUP_DIR)/live-db-$(STAMP).sql.gz

release: ## Export REF into backups/.release (used by deploy)
	@commit=$$(git rev-parse -q --verify "$(REF)^{commit}") || { echo "Unknown ref '$(REF)'."; exit 1; }; \
	rm -rf $(RELEASE_DIR) && mkdir -p $(RELEASE_DIR) && git archive "$$commit" | tar -x -C $(RELEASE_DIR) && \
	git log -1 --format='%H %ad %s' --date=short "$$commit" > $(RELEASE_DIR)/REVISION && \
	echo "Prepared $$(cat $(RELEASE_DIR)/REVISION)"

deploy-dry-run: release ## Show which files a deploy of REF would change on live
	rsync -azc -n -v $(RSYNC_EXCLUDE) $(RELEASE_DIR)/ $(LIVE_HOST):$(LIVE_DIR)/
	@for d in $(MANAGED_DIRS); do [ -d $(RELEASE_DIR)/$$d ] || continue; \
		echo "--- $$d (with --delete)"; \
		rsync -azc -n -v --delete $(RELEASE_DIR)/$$d/ $(LIVE_HOST):$(LIVE_DIR)/$$d/ || exit 1; \
	done

deploy: release ## Deploy REF (default master) to live: backup, sync, build, updb, cim
	@$(LIVE_SSH) 'grep -q "^DRUPAL_SMTP_PASSWORD=." $(LIVE_DIR)/.env' \
		|| { echo "DRUPAL_SMTP_PASSWORD is missing in $(LIVE_DIR)/.env on live; mail would stop working."; exit 1; }
	$(MAKE) live-backup STAMP=$(STAMP)
	$(LIVE_DRUSH) state:set system.maintenance_mode 1 --input-format=integer
	@{ rsync -azc $(RSYNC_EXCLUDE) $(RELEASE_DIR)/ $(LIVE_HOST):$(LIVE_DIR)/ && \
	  for d in $(MANAGED_DIRS); do [ -d $(RELEASE_DIR)/$$d ] || continue; \
	    rsync -azc --delete $(RELEASE_DIR)/$$d/ $(LIVE_HOST):$(LIVE_DIR)/$$d/ || exit 1; \
	  done && \
	  $(LIVE_SSH) 'set -e; cd $(LIVE_DIR); \
		$(LIVE_DC) build drupal; \
		$(LIVE_DC) up -d; \
		until docker exec $(LIVE_CTR) vendor/bin/drush status --field=bootstrap 2>/dev/null | grep -q Successful; do sleep 2; done; \
		docker exec $(LIVE_CTR) vendor/bin/drush updb -y; \
		docker exec $(LIVE_CTR) vendor/bin/drush cim -y; \
		for f in web/modules/custom/*/translations/*.nl.po; do [ -f "$$f" ] || continue; \
			docker exec $(LIVE_CTR) vendor/bin/drush locale:import nl "/opt/drupal/$$f" --type=not-customized --override=not-customized; \
		done; \
		docker exec $(LIVE_CTR) vendor/bin/drush simple-sitemap:generate; \
		docker exec $(LIVE_CTR) vendor/bin/drush cr; \
		docker exec $(LIVE_CTR) vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer; \
		docker exec $(LIVE_CTR) vendor/bin/drush cr'; } \
		|| { echo "Deploy failed; the site is left in maintenance mode. Backup: $(BACKUP_DIR)/live-db-$(STAMP).sql.gz"; exit 1; }
	@echo "Deployed $$(cat $(RELEASE_DIR)/REVISION). Backup: $(BACKUP_DIR)/live-db-$(STAMP).sql.gz"
