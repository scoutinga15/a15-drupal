# A15 website #

## How to build the website? ##

** Run npm**

Droopler is using Gulp stack to speed up development of new sites. It compiles SCSS to CSS, enables Autoprefixer to deal with browser compatibility and minimizes all JavaScript files. [Install Node v13 and npm](https://nodejs.org/en/download/) on your computer and in the root directory of your project run the following commands:

```sh
$ npm install --global gulp-cli
$ cd web/profiles/contrib/droopler/themes/custom/droopler_theme
$ npm install
$ gulp compile
$ cd -
$ cd web/themes/custom/droopler_subtheme
$ npm install
$ gulp compile
```

There are also other Gulp commands for theme developers, here's the full reference:

 - **gulp watch** - watches for changes in SCSS and JS and proceses them on the fly
 - **gulp compile** - cleans derivative files and compiles all SCSS/JS in the subtheme for DEV environment
 - **gulp dist** - cleans derivative files and compiles all SCSS/JS in the subtheme for PROD environment
 - **gulp clean** - cleans derivative files
 - **gulp debug** - prints Gulp debug information, this comes in handy when something's not working

## SCSS structure ##

 - **style.scss** - combines all SCSS code from base theme and subtheme
 - **print.scss** - combines all SCSS code for printing from base theme and subtheme
 - **config/** - the most important directory that contains the subtheme configuration - you can add your own config files like _foobar.scss, just refer to them in _all.scss.
 - **libraries/** - additional files needed by Drupal

You can use any SCSS structure you like. We recommend dividing files into **layout/** and **components/** directories. Just remember to include your files in **style.scss**.

## SCSS Configuration ##

Droopler is designed to make your work easier. You don't have to override SCSS or CSS code to make your own adjustments. In most cases it is enough to modify the configuration. Just look into variable definitions in the subtheme's **scss/config/_base_theme_overrides.scss** file.

```scss
// Colours - The Greeks
// -------------------------
// $color-odysseus: white;

// Paragraph d_p_banner
// -------------------------
// $d-p-banner-header-color: $color-odysseus;
// $d-p-banner-subheader-color: $color-odysseus;
```

To alter this - uncomment the line and change the value. A you can see - there are many levels of variables, see the comments in _base_theme_overrides.scss to get some more information.

When you save this config file, **gulp watch** will recompile all SCSS with your own config.

## Updating Droopler ##

See the [UPDATE.md](https://github.com/droptica/droopler/blob/master/UPDATE.md) file from the Droopler profile.

## How to install Google Fonts? ##

By default Droopler uses free [Lato](http://www.latofonts.com/) webfont. If you wish to install your own fonts from Google - put their definitions into **droopler_subtheme.libraries.yml** like this:

```yaml
global-styling:
  version: VERSION
  css:
    theme:
      '//fonts.googleapis.com/css?family=Rajdhani:500,600,700|Roboto:400,700&subset=latin-ext': { type: external, minified: true }
      css/style.css: {}
```

## How to install icon fonts? ##

If you wish to install FontAwesome or Glyphicons from the CDN - just grab their URLs and follow the steps described in previous chapter about Google Fonts. You'll find a FontAwesome example in **droopler_subtheme.libraries.yml** and **droopler_subtheme.info.yml**.

## Local development ##

```shell
cp local.env .env      # first time only
make build             # build and start the containers (http://localhost:8081)
make live-db-pull      # replace the local database with a copy of live
make help              # all targets
```

`config/sync` and `web/modules/custom` are mounted into the container, so `make cex` / `make cim` work on the repository directly.

## Deployment ##

Live runs with Docker Compose (both compose files) on `94.130.98.128` in `/root/projects/a15-drupal`, behind nginx-proxy-manager on the external `npm` network. That directory is not a git clone: `make deploy` exports the commit with `git archive` and uploads it with rsync. The server's `.env` holds the database settings, `DRUPAL_FILES_PUBLIC` and `DRUPAL_SMTP_PASSWORD`; public files are in `web/sites/default/files` there.

```shell
make live-cex                 # pull admin changes from live into config/sync first
make deploy-dry-run REF=master  # list the files a deploy would change on the server
make deploy REF=master        # backup, maintenance mode, sync, build, updb, cim
make live-status              # deployed commit, Drupal status, pending updates/config
```

The Drupal 9.3 → 10 upgrade is a two-step deploy: see [docs/drupal-10-upgrade.md](docs/drupal-10-upgrade.md).

`make deploy` refuses to run when `DRUPAL_SMTP_PASSWORD` is missing from the server's `.env`. Every deploy saves a database dump in `backups/`. When a deploy fails, the site stays in maintenance mode; restore the backup or fix and deploy again.

## Backups ##

```shell
make live-backup              # live database  -> backups/live-db-<date>.sql.gz
make live-files-backup        # live files     -> backups/live-files-<date>.tgz
make db-backup                # local database -> backups/local-db-<date>.sql.gz
make db-restore FILE=backups/<dump>.sql.gz
```
