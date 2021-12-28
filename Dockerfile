FROM php:8.0-apache-buster

# install the PHP extensions we need
RUN set -eux; \
	\
	if command -v a2enmod; then \
		a2enmod rewrite; \
	fi; \
	\
	savedAptMark="$(apt-mark showmanual)"; \
	\
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg-dev \
		libpng-dev \
		libpq-dev \
		libzip-dev \
	; \
	docker-php-ext-configure gd \
		--with-freetype \
		--with-jpeg=/usr \
	; \
	\
	docker-php-ext-install -j "$(nproc)" \
		gd \
		opcache \
		pdo_mysql \
		pdo_pgsql \
		zip \
	; \
	\
# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { print $3 }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	\
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
  apt-get install -y  \
    git  \
    nodejs  \
    npm \
    mariadb-client \
  ;\
	rm -rf /var/lib/apt/lists/*

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
		echo 'opcache.fast_shutdown=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini

RUN { \
    echo 'upload_max_filesize=200M'; \
    echo 'post_max_size=200M'; \
    echo 'max_execution_time=120'; \
  } > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/

# Upgrading Node
RUN npm cache clean -f
RUN npm install -g n
RUN n stable

RUN npm install --global gulp-cli

WORKDIR /opt/drupal

COPY config config/
COPY drush drush/
COPY composer.json .
COPY composer.lock .
COPY composer.patches.json .
COPY web/assets web/assets/
COPY web/themes web/themes/
COPY web/modules web/module/
COPY web/sites/default web/sites/default/

RUN set -eux; \
	export COMPOSER_HOME="$(mktemp -d)"; \
  composer install --no-dev --no-interaction; \
	#composer create-project --no-interaction "drupal/recommended-project:$DRUPAL_VERSION" ./; \
	chown -R www-data:www-data config web/sites web/modules web/themes; \
	rmdir /var/www/html; \
	ln -sf /opt/drupal/web /var/www/html; \
	# delete composer cache
	rm -rf "$COMPOSER_HOME"

RUN cd web/profiles/contrib/droopler/themes/custom/droopler_theme && npm install && gulp dist
RUN cd web/themes/custom/droopler_a15 && npm install && gulp dist

ENV PATH=${PATH}:/opt/drupal/vendor/bin
