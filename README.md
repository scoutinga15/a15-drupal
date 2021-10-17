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

## Docker setup ##

Start nginx-proxy with the three additional volumes declared

```shell
docker run --detach \
    --name nginx-proxy \
    --publish 80:80 \
    --publish 443:443 \
    --volume /mnt/volume_ams3_01/proxy/certs:/etc/nginx/certs \
    --volume /mnt/volume_ams3_01/proxy/vhost:/etc/nginx/vhost.d \
    --volume /mnt/volume_ams3_01/proxy/html:/usr/share/nginx/html \
    --volume /var/run/docker.sock:/tmp/docker.sock:ro \
    nginxproxy/nginx-proxy
```

Step 2 - acme-companion
```shell
docker run --detach \
    --name nginx-proxy-acme \
    --volumes-from nginx-proxy \
    --volume /var/run/docker.sock:/var/run/docker.sock:ro \
    --volume /mnt/volume_ams3_01/proxy/acme:/etc/acme.sh \
    --env "DEFAULT_EMAIL=dev@j3ll3.nl" \
    nginxproxy/acme-companion
```

Drupal
```shell
docker run --detach \
    --name drupal \
    --env "VIRTUAL_HOST=nieuw.scoutinga15.nl" \
    --env "LETSENCRYPT_HOST=nieuw.scoutinga15.nl" \
    drupal
```

Volumes
- assets
- modules
- profiles
- themes

`scp -rp files root@159.65.196.174:/mnt/volume_ams3_01/a15/drupal-files `

## Portainer ##

```shell
docker run -d --name portainer \
    --restart=always \
    -v /var/run/docker.sock:/var/run/docker.sock \
    -v portainer_data:/data \
    --env "VIRTUAL_HOST=portainer.j3host.nl" \
    --env "LETSENCRYPT_HOST=portainer.j3host.nl" \
    --env "VIRTUAL_PORT=9000" \
    portainer/portainer-ce:latest
```

## Files and database ##

SCP files to server
```shell
scp -rp files root@159.65.196.174:/mnt/volume_ams3_01/a15/drupal-files
```

sql dump
```shell
drush sql-dump > ./a15-local-$(date +%Y-%m-%d-%H.%M.%S).sql
```

gzip sql
```shell
drush sql-dump | gzip -9 > ./a15-local-$(date +%Y-%m-%d-%H.%M.%S).sql
```
