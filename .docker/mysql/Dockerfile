FROM mysql:9.5

COPY docker-entrypoint-initdb.d /docker-entrypoint-initdb.d
COPY conf.d/collation.cnf /etc/mysql/conf.d/collation.cnf
COPY my.cnf /etc/my.cnf
