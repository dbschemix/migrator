# Database Migrator

Консольная программа для управления миграциями.

Консольная утилита на основе:
- https://github.com/dbschemix/core
- https://github.com/dbschemix/pdo
- https://github.com/symfony/console

### Command

- **init** — инициализация проекта: создание папки для миграций и конфигурационного файла.
- **up** — применение всех ожидающих миграций до самой свежей.
- **down** — откат последней примененной миграции (или нескольких).
- **fixture** — применение всех фикстур.
- **create** — создание файла миграции (удобно при разработке).
- **verify** — последовательный запуск up и сразу down для последней версии миграций (удобно при разработке).
- **redo** — последовательный запуск down и сразу up для последней миграции (удобно при разработке).

### setup

Например, для базы данных с именем _main_ под управлением сервера **postgres**:
```shell
mkdir -p ./migration/pgsql/{main,main-fixture} 
```

Описываем конфигурацию:
```php
$migrator = new Migrator(
    list: [
        new Migration(
            path: __DIR__ . '/migration/postgres/main',
            driver: new PdoDriver(
                dsn: 'pgsql:host=postgres;port=5432;dbname=main',
                username: 'postgres',
                password: 'postgres',
            )
        )
    ],
);
```

### migration

Команды миграции описываются на языке SQL, например:
```sql
-- @up
CREATE TABLE IF NOT EXISTS public.entity (
    id serial NOT NULL,
    parent_id integer NOT NULL,
    created_at timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT entity_pkey PRIMARY KEY (id)
);
CREATE INDEX IF NOT EXISTS "I_entity_parent_id" ON public.entity USING btree (parent_id);

-- @down
DROP INDEX IF EXISTS I_entity_parent_id;
DROP TABLE IF EXISTS public.entity;
```

Управляющие команды:

- `@up`
- `@down`
- `@skip`

Если команды не указаны, то весь код будет вычитан как секция `up`.  
Если нужно скипнуть файл целиком, то можно добавить в название постфикс `skip`, например `202501011025_name_skip.sql`

### CLI application

```php
use dbschemix\pdo\Driver;
use dbschemix\core\Migration;
use dbschemix\core\Migrator;
use dbschemix\migrator\cmd\Console;
use dbschemix\migrator\tools\PrettyConsoleOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

$migrator = new Migrator(
    list: [
        new Migration(
            path: __DIR__ . '/migration/sqlite/memory',
            driver: new Driver(
                dsn: 'sqlite:' . __DIR__ . '/data/sqlite/db.sqlite3',
            )
        ),
    ],
    eventSubscribers: [
        new PrettyConsoleOutput(),
    ],
);

Console::run($migrator);
```

### Example

```shell
make app
```

```shell
/example $ php cli.php migrate:init
[sqlite/db] initialization: setup.sql done

/example $ php cli.php migrate:up
[sqlite/db] up: 202501011024_entity_create.sql done
[sqlite/db] up: 202501021024_account_create.sql done
[sqlite/db] up: 202501021025_account_email.sql done

/example $ php cli.php migrate:down
[sqlite/db] down: 202501021025_account_email.sql done
[sqlite/db] down: 202501021024_account_create.sql done
[sqlite/db] down: 202501011024_entity_create.sql done
```

#### With exactly all

If any migration fails, the entire batch is rolled back, leaving the database unchanged.

```shell
/example $ php cli.php migrate:up --exactly-all
[sqlite/db] up: 202501011024_entity_create.sql done
[sqlite/db] up: 202501021024_account_create.sql done
[sqlite/db] up: 202501021025_account_email.sql done
```

#### With repeatable

```shell
/example $ php cli.php migrate:up --with-repeatable
[sqlite/db] up: 202501011024_entity_create.sql done
[sqlite/db] up: 202501021024_account_create.sql done
[sqlite/db] up: 202501021025_account_email.sql done
[sqlite/db] repeatable: 202501011024_entity_correction.sql done
[sqlite/db] repeatable: 202501011024_entity_correction_2.sql done
```

#### Down with latest version

```shell
/example $ php cli.php migrate:up --limit=1
[sqlite/db] up: 202501011024_entity_create.sql, vers: 1772723563954 done

/example $ php cli.php migrate:up --limit=2
[sqlite/db] up: 202501021024_account_create.sql, vers: 1772723566084 done
[sqlite/db] up: 202501021025_account_email.sql, vers: 1772723566084 done

/example $ php cli.php migrate:down --latest-version
[sqlite/db] down: 202501021025_account_email.sql, vers: 1772723566084 done
[sqlite/db] down: 202501021024_account_create.sql, vers: 1772723566084 done

```

#### Redo with latest version

```shell
/example $ php cli.php migrate:up
[sqlite/db] up: 202501021024_account_create.sql, vers: 1772723718828 done
[sqlite/db] up: 202501021025_account_email.sql, vers: 1772723718828 done

/example $ php cli.php migrate:redo --latest-version
[sqlite/db] down: 202501021025_account_email.sql, vers: 1772723718828 done
[sqlite/db] down: 202501021024_account_create.sql, vers: 1772723718828 done
[sqlite/db] up: 202501021024_account_create.sql, vers: 1772723727397 done
[sqlite/db] up: 202501021025_account_email.sql, vers: 1772723727397 done

```

#### Verify

```shell
/example $ php cli.php migrate:create test --db=sqlite/db                                                                                   
/example $ php cli.php migrate:create test2 --db=sqlite/db        
/example $ php cli.php migrate:create test3 --db=sqlite/db

/example $ php cli.php migrate:verify
[sqlite/db] up: 202603070850_test.sql, vers: 177287432696 done
[sqlite/db] up: 202603070850_test2.sql, vers: 177287432696 done
[sqlite/db] up: 202603070850_test3.sql, vers: 177287432696 done
[sqlite/db] down: 202603070850_test3.sql, vers: 177287432696 done
[sqlite/db] down: 202603070850_test2.sql, vers: 177287432696 done
[sqlite/db] down: 202603070850_test.sql, vers: 177287432696 done
```

**With limit**

```shell
/example $ php cli.php migrate:verify --limit=1
[sqlite/db] up: 202603070850_test.sql, vers: 177287441498 done
[sqlite/db] down: 202603070850_test.sql, vers: 177287441498 done

```

**error**

```shell

/example $ php cli.php migrate:verify
[sqlite/db] up: 202603070850_test.sql, vers: 177287479980 done
[sqlite/db] up: 202603070850_test2.sql error
SQLSTATE[HY000]: General error: 1 incomplete input
-- SQL CODE
INSERT INTO ededede


[sqlite/db] down: 202603070850_test.sql, vers: 177287479980 done
202603070850_test2.sql: SQLSTATE[HY000]: General error: 1 incomplete input
```

### Docker / docker-compose

The library ships a thin runtime image. The image contains PHP, the
`pdo_mysql` / `pdo_pgsql` / `pdo_sqlite` extensions and an entrypoint — it
does **not** contain the library. The library and your custom code
(e.g. `eventSubscribers`) come from your project's mounted `vendor/`.

**Config contract.** Provide a PHP file that returns the `Migrator`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use dbschemix\pdo\Driver;
use dbschemix\core\{Migration, Migrator};
use dbschemix\migrator\tools\PrettyConsoleOutput;

$migrator = new Migrator(
    list: [
        new Migration(
            path: __DIR__ . '/migration/pgsql/main',
            driver: new Driver('pgsql:host=postgres;port=5432;dbname=main', 'postgres', 'postgres'),
        ),
    ],
    eventSubscribers: [
        new PrettyConsoleOutput(),
    ],
);

return $migrator;
```

The file is responsible for its own autoload and must end with
`return $migrator;`. Resolve paths with `__DIR__` so they work inside the
mounted container. `eventSubscribers` is plain PHP — list instances of any
class (including your own), no special notation.

**docker-compose service.** Mount your project, point `MIGRATOR_CONFIG` at
the config file, and pass the migration command via `command:`:

```yaml
services:
  migrator:
    image: ghcr.io/dbschemix/migrator:1
    init: true
    environment:
      MIGRATOR_CONFIG: /app/migrator.php
    volumes:
      - ./:/app
    command: ["migrate:up", "--limit=1"]
    depends_on:
      postgres:
        condition: service_healthy
```

`MIGRATOR_CONFIG` defaults to `/app/migrator.php`. `init: true` ensures
signals (e.g. `docker compose stop`) are delivered cleanly. Any
`migrate:*` command and its options are accepted, exactly as in the CLI.

A runnable sqlite example lives in `example/docker/migrator.php`; build and
exercise it locally with `make docker-runtime`.
