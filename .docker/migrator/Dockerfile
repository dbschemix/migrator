ARG PHP_VERSION=8.3

FROM ghcr.io/kuaukutsu/php:${PHP_VERSION}-cli

# Database drivers the library can use via dbschemix/pdo
RUN install-php-extensions pdo_mysql pdo_pgsql pdo_sqlite

# Entrypoint shim (the library itself comes from the user's mounted vendor/)
COPY migrator /usr/local/bin/migrator
RUN chmod +x /usr/local/bin/migrator

# Unprivileged user; override with `-u $(id -u)` to match host file ownership
RUN adduser -D -H -s /sbin/nologin migrator

ENV MIGRATOR_CONFIG=/app/migrator.php
WORKDIR /app
USER migrator

ENTRYPOINT ["php", "/usr/local/bin/migrator"]
CMD []
