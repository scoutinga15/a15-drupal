# Upgrade Drupal 9.3 → 10.6

Runbook for upgrading the live site from Drupal 9.3.8 (Droopler 3.0, PHP 7.4) to Drupal 10.6 (Droopler 3.3, PHP 8.3).

The upgrade has two commits on `feature/update-drupal`, and **each one is deployed separately, in this order**:

| Step | Commit | Result |
|------|--------|--------|
| 1 | `b457418` Upgrade to Drupal 9.5 as intermediate step to Drupal 10 | Drupal 9.5.11, Droopler 8.3.2.0-rc1, Drush 11, PHP 8.1 |
| 2 | `295a274` Upgrade to Drupal 10.6 and deploy with the Makefile | Drupal 10.6.17, Droopler 8.3.3.0, Drush 13, PHP 8.3 |

## Why two steps

- Drupal 10 cannot update a 9.3 database. The site has to run the database updates of 9.4/9.5 first.
- Droopler's own upgrade guide requires the same: on 9.5, uninstall `lazy` and `rdf` and move to CKEditor 5, then update to Droopler 3.3 and Drupal 10.
- Modules removed from Drupal 10 core (CKEditor 4, `color`, `rdf`) and modules without a Drupal 10 release (`swiftmailer`) must be uninstalled while their code still exists. Step 1 does that through `config/sync`. Step 2 then removes the code.

## What changes

### Step 1: Drupal 9.5

- Core 9.5.11, Droopler 8.3.2.0-rc1, Drush 11; `drupal/console` and `zaporylie/composer-drupal-optimizations` removed.
- Docker image: `php:8.1-apache-bookworm`, `memory_limit=512M`.
- Uninstalled via config: `lazy`, `rdf`, `color`, `ckeditor`, `swiftmailer`.
- Enabled via config: `ckeditor5`, `symfony_mailer_lite`. The `full_html` and `webform_default` text formats use CKEditor 5.
- Mail: `symfony_mailer_lite` with an SMTP transport to `send.one.com`. The password comes from the `DRUPAL_SMTP_PASSWORD` environment variable (`settings.php`), not from config.
- `settings.php`: database driver namespace `Drupal\mysql\Driver\Database\mysql` (the old one is removed in Drupal 10).
- Booking module and theme: jQuery `once()` replaced with `core/once`.
- `patches/honeypot-update-8102-skip-existing-id.patch`: an old patch already added honeypot's `id` column, so update 8102 would fail without it.
- `composer.json` temporarily sets `audit.block-insecure: false` (9.5 is end-of-life) and declares `provide: drupal/contact` (core 9.5's metadata omits it). Both are removed in step 2.

### Step 2: Drupal 10.6

- Core 10.6.17, Droopler 8.3.3.0, Drush 13, `symfony_mailer_lite` 2.x; advisory blocking back on.
- Docker image: `php:8.3-apache-bookworm`.
- `twig/twig` pinned to `~3.29.0`: Twig 3.30.0 breaks Drupal 10.6.17's escape filter (every page returns 500).
- `drupal/we_megamenu` pinned to `1.16.0`: Droopler 8.3.3.0's patches don't apply to 1.17.
- Honeypot and advagg patches dropped (fixed upstream).
- `deploy.php` (Deployer, old DigitalOcean server) replaced by Makefile targets.

## Before deploying

1. **SMTP password on the server.** Done on 2026-09-27: `DRUPAL_SMTP_PASSWORD` was added to `/root/projects/a15-drupal/.env` with the password live used at that time (backup: `.env.bak-20260926-224802`). That password is also in git history, so change it at one.com and update `.env` afterwards. `make deploy` refuses to run when the variable is missing.
2. **Preview the changes.** `make deploy-dry-run REF=b457418` lists the files a deploy would change or delete on the server, without changing anything.
3. **Export live config.** If anything was changed in the admin UI since the export on 2026-09-27, it would be overwritten by `cim`. Run `make live-cex` and check `git diff config/sync`; commit real changes to both steps (rebase) before deploying.
4. **Rehearse locally with live data.** Done on 2026-09-27 with a fresh live dump: both steps ran without errors, config ended in sync, and all public, admin and booking pages worked. To repeat it, run each step with its own code, and load the dump while the step 1 code is running. A cache rebuild with Drupal 10 code on the 9.3 database breaks it (`Route "webform.addons" does not exist`).
   ```shell
   make live-backup                                   # backups/live-db-<date>.sql.gz
   git worktree add ../a15-step1 b457418 && cp .env ../a15-step1/
   cd ../a15-step1
   dc() { docker compose -p a15-drupal -f docker-compose.yml -f docker-compose.override.yml "$@"; }
   dc up -d --build drupal
   dc exec -T drupal vendor/bin/drush sql:drop -y
   gunzip -c ../a15-drupal/backups/live-db-<date>.sql.gz | dc exec -T drupal vendor/bin/drush sql:cli
   dc exec -T drupal vendor/bin/drush updb -y && dc exec -T drupal vendor/bin/drush cim -y
   cd ../a15-drupal && git worktree remove ../a15-step1
   make build && make updb && make cim && make drush cmd=simple-sitemap:generate && make cr
   ```
   Then check the site on http://localhost:8081 (see [Verify](#verify)).
5. **Plan a quiet moment.** The site is in maintenance mode during each deploy; the image build takes a few minutes.

## Deploy

```shell
make deploy REF=b457418     # step 1: Drupal 9.5
make live-status            # verify, see below
make deploy REF=295a274     # step 2: Drupal 10.6
make live-status
```

`make deploy` does, in order:

1. Exports the commit with `git archive` into `backups/.release` and writes a `REVISION` file.
2. Checks that `DRUPAL_SMTP_PASSWORD` is set in the server's `.env`.
3. Downloads a database backup to `backups/live-db-<date>.sql.gz`.
4. Turns on maintenance mode.
5. Uploads the release with rsync (see [Server layout](#server-layout)).
6. Rebuilds the image and restarts the containers.
7. Runs `drush updb -y`, `drush cim -y`, `drush simple-sitemap:generate` and `drush cr`.
8. Turns off maintenance mode.

The sitemap is regenerated because the simple_sitemap updates in step 1 empty its table; without it, `/sitemap.xml` returns 404 until cron runs (every 3 hours on live).

The first deploy also removes `web/modules/custom/clubhouse_booking/translations/nl.po` from the server: an old copy that was never in git and is not loaded (the module uses `clubhouse_booking.nl.po`).

If any step fails, the site **stays in maintenance mode** and the backup path is printed.

Expected output that is not an error: during step 2, `droopler_update_8139` logs `Detected changes in configuration advagg_js_minify.settings` and skips an optional part. The value it wants to set (`minifier: 4`) is already in place.

## Server layout

Checked on 2026-09-27:

- `/root/projects/a15-drupal` on `94.130.98.128` is a plain copy of the repository, not a git clone. It matched commit `b540dea` apart from the old `nl.po`.
- The containers run with both `docker-compose.yml` and `docker-compose.override.yml` (port 8081, custom modules mounted, `npm` network). `make deploy` uses both files, so this stays the same.
- Public files (576 MB) live in `web/sites/default/files` inside the project directory and are mounted into the container.
- rsync 3.2.7 and Docker Compose v2.38 are installed.

rsync uploads every tracked file but only deletes stale files in `config/`, `patches/`, `web/modules/custom/` and `web/themes/custom/`. It never touches `.env`, `local.env`, `backups/`, `web/sites/*/files/` or `web/sites/*/settings.local.php`.

## Verify

After each step:

- `make live-status` shows the new Drupal version, no pending updates and `No differences between DB and sync directory`.
- The home page, a few content pages, `/contact`, `/clubhouse/booking` and `/sitemap.xml` load.
- Logged in: a node edit form shows CKEditor 5; `/admin/clubhouse/booking/slots` and `/admin/reports/status` load.
- Mail works: submit the contact form or a booking request and check it arrives.
- `/admin/reports/dblog` shows no new PHP errors.

## Rollback

There is no automated rollback. To go back to the state before a deploy, put the previous code back and restore the database backup that `make deploy` made. Before step 1, live matches commit `b540dea`; before step 2, it is `b457418`.

```shell
# 1. Put the previous code back on the server and rebuild.
#    make deploy would first take a new backup and run updb/cim, so sync and build by hand:
make release REF=<previous-commit>
rsync -azc --exclude=/.env --exclude=/local.env --exclude=/backups/ --exclude='/web/sites/*/files/' \
  backups/.release/ root@94.130.98.128:/root/projects/a15-drupal/
for d in config web/modules/custom web/themes/custom; do
  rsync -azc --delete backups/.release/$d/ root@94.130.98.128:/root/projects/a15-drupal/$d/
done
ssh root@94.130.98.128 'cd /root/projects/a15-drupal && docker compose -f docker-compose.yml -f docker-compose.override.yml up -d --build'

# 2. Restore the database backup made by make deploy
gunzip -c backups/live-db-<date>.sql.gz | ssh root@94.130.98.128 'docker exec -i a15-drupal-drupal-1 vendor/bin/drush sql-cli'
ssh root@94.130.98.128 'docker exec a15-drupal-drupal-1 vendor/bin/drush cr'

# 3. Turn maintenance mode off if it is still on
ssh root@94.130.98.128 'docker exec a15-drupal-drupal-1 vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer'
```
 Always restore the database together with the code: Drupal 9.5 code cannot run on a database that Drupal 10 updated, and the reverse.

## Follow-up

- Remove the `twig/twig` pin once a Drupal 10.6 release supports Twig 3.30.
- Drop the `drupal/we_megamenu` pin when Droopler updates its patches, or when they are no longer needed.
- Drupal 11 is blocked by Droopler: the 3.x line stops at Drupal 10. Drupal 11 means rebuilding on Droopler 5 (a separate product) or removing the Droopler profile. Drupal 10 security support ends some time after Drupal 12 is released.
