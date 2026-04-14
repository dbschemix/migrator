ARG PHP_VERSION=8.3

FROM ghcr.io/kuaukutsu/php:${PHP_VERSION}-cli AS build

# Arguments
ARG UID=10001
ARG WORKDIR="/src"

# Configure
COPY conf/php.ini /usr/local/etc/php/php.ini
# Dependencies
COPY --from=composer:latest --link /usr/bin/composer /usr/bin/composer
# user
RUN <<EOF
    adduser -u ${UID} -G www-data -s /bin/sh -D developer www-data
EOF
# workdir
RUN <<EOF
    mkdir -p ${WORKDIR}
    chown -R developer:www-data ${WORKDIR}
EOF
# composer
RUN <<EOF
    mkdir /var/.composer
    chown developer:www-data /var/.composer
EOF
ENV COMPOSER_CACHE_DIR=/var/.composer

FROM build AS devel

RUN install-php-extensions xdebug

USER $UID
WORKDIR $WORKDIR

FROM build AS tests

RUN install-php-extensions pcov

USER $UID
WORKDIR $WORKDIR
