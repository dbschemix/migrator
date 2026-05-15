# Дизайн: подключение мигратора как сервиса в docker-compose

- **Дата:** 2026-05-15
- **Статус:** утверждён (готов к написанию плана реализации)

## Цель

Дать возможность удобно подключать библиотеку `dbschemix/migrator` в docker-compose
окружение через публикуемый ghcr-образ, подключаемый как сервис (`image:` + `command:`),
без написания пользователем собственного Dockerfile.

## Зафиксированные решения

- **Формат конфига:** прямой PHP-файл, возвращающий `return $migrator;`. Слоя парсинга
  YAML/JSON нет.
- **Autoload:** конфиг пользователя сам делает `require '.../vendor/autoload.php'`
  (включая саму библиотеку и кастомные классы).
- **Образ:** тонкий runtime (PHP CLI + pdo-расширения + bootstrap-скрипт), без библиотеки.
  Версия библиотеки определяется composer'ом пользователя, не тегом образа.
- **PHP-код библиотеки не меняется** — `Console::run()` уже делает `exit($code)`,
  что корректно для контейнера.
- **Механика entrypoint:** PHP-bootstrap как `ENTRYPOINT`, docker `command:` попадает
  в `$argv`, Symfony Console читает его сам.
- **Обнаружение конфига:** env-переменная `MIGRATOR_CONFIG`, значение по умолчанию
  `/app/migrator.php`.
- **Deliverables:** Dockerfile + entrypoint, CI-публикация в ghcr, пример интеграции,
  раздел README.

## 1. Архитектура

Поток запуска:

```
docker compose run migrator migrate:up --limit=1
        │
        ▼
ENTRYPOINT ["php","/usr/local/bin/migrator"]   argv = [script, "migrate:up", "--limit=1"]
        │
        ▼
migrator (bootstrap):
   1. $config = getenv('MIGRATOR_CONFIG') ?: '/app/migrator.php'
   2. проверки: файл существует и читается
   3. $migrator = require $config;          // конфиг сам require'ит vendor/autoload.php
   4. проверка: $migrator instanceof \dbschemix\core\MigratorInterface
   5. \dbschemix\migrator\cmd\Console::run($migrator)
        │
        ▼
Symfony Application читает $argv → выполняет команду → exit(code)
```

Границы ответственности:

- **Образ** — воспроизводимая среда выполнения (PHP + pdo + bootstrap).
- **Конфиг пользователя** — wiring: autoload, `Migration[]`, драйверы, `eventSubscribers`.
- **docker `command:`** — какую миграционную команду выполнить.

## 2. Компоненты

### 2.1. Bootstrap-скрипт `migrator`

Расположение в репозитории: `.docker/migrator/migrator`.

PHP-скрипт (~40 строк). Единственная логика: резолв пути к конфигу, валидация
контракта, делегирование в `Console`. Сам **не** require'ит autoload — это делает
конфиг. На `\dbschemix\migrator\cmd\Console` ссылается строкой полного FQCN уже
**после** `require $config`, поэтому отсутствие библиотеки на этапе парсинга
скрипта не является проблемой.

Логика резолва/валидации выносится в маленькую тестируемую единицу (функция или
класс), а тонкий `migrator` лишь вызывает её и затем `Console::run()`.

Контракт конфиг-файла (документируется в README):

- файл заканчивается `return $migrator;`;
- конфиг сам отвечает за `require '.../vendor/autoload.php'`;
- пути миграций задаются через `__DIR__` (резолвятся внутри контейнера при
  монтировании проекта пользователя).

### 2.2. Dockerfile

Расположение: `.docker/migrator/Dockerfile`. Контекст сборки — `.docker/migrator`
(как у существующих сервисов `postgresql`/`mysql`), поэтому корневой `.dockerignore`
(исключающий `.docker`) не мешает `COPY`.

- база: `ghcr.io/kuaukutsu/php:${PHP_VERSION}-cli` (уже используется в проекте,
  `PHP_VERSION` по умолчанию `8.3`);
- `install-php-extensions pdo_mysql pdo_pgsql pdo_sqlite`;
- `COPY migrator /usr/local/bin/migrator` + `chmod +x`;
- непривилегированный пользователь;
- `ENV MIGRATOR_CONFIG=/app/migrator.php`;
- `ENTRYPOINT ["php","/usr/local/bin/migrator"]`, `CMD []`.

### 2.3. CI-workflow

Расположение: `.github/workflows/docker-publish.yml`. Триггер: `release: [published]`
(как у существующего `published.yml`).

- `permissions: packages: write`;
- `docker/login-action` с `GITHUB_TOKEN`;
- `docker/metadata-action` → теги: семвер из релиза (`X.Y.Z`, `X.Y`, `X`) + `latest`;
- `docker/build-push-action`, контекст `.docker/migrator`,
  платформы `linux/amd64,linux/arm64`;
- образ: `ghcr.io/dbschemix/migrator`.

### 2.4. Пример интеграции

- `example/docker/migrator.php` — конфиг в новом контракте
  (`require autoload` + `return new Migrator(...)`), на базе существующего
  `example/cli.php` (sqlite), с `PrettyConsoleOutput` в `eventSubscribers`.
- Сниппет `docker-compose` в README.

### 2.5. README

Новый раздел «Docker / docker-compose»: контракт конфига, `MIGRATOR_CONFIG`,
монтирование проекта, примеры `command:`, заметка про `init: true`
(сигналы / PID 1).

## 3. Поток данных и пример использования

Конфиг пользователя `migrator.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use dbschemix\pdo\Driver;
use dbschemix\core\{Migration, Migrator};
use dbschemix\migrator\tools\PrettyConsoleOutput;
use App\Migration\AuditSubscriber; // кастомный класс пользователя

$migrator = new Migrator(
    list: [
        new Migration(
            path: __DIR__ . '/migration/pgsql/main',
            driver: new Driver('pgsql:host=postgres;port=5432;dbname=main', 'postgres', 'postgres'),
        ),
    ],
    eventSubscribers: [
        new PrettyConsoleOutput(),
        new AuditSubscriber(),
    ],
);

return $migrator;
```

docker-compose у пользователя:

```yaml
services:
  migrator:
    image: ghcr.io/dbschemix/migrator:1
    init: true
    environment:
      MIGRATOR_CONFIG: /app/migrator.php
    volumes:
      - ./:/app          # проект + vendor + migrations + конфиг
    command: ["migrate:up", "--limit=1"]
    depends_on:
      postgres:
        condition: service_healthy
```

`eventSubscribers` как массив class-string решается естественно: пользователь
пишет `new ClassName()` (массив инстансов) прямо в PHP — никакой нотации/маппинга
не требуется, кастомные классы доступны через его же `vendor/autoload.php`.

## 4. Обработка ошибок

Bootstrap-скрипт даёт понятные сообщения в `stderr` и ненулевой exit-код:

| Ситуация | Поведение |
|---|---|
| Конфиг не найден / не читается | путь + подсказка про `MIGRATOR_CONFIG`/volume; `exit(1)` |
| Конфиг бросил `Throwable` при `require` (например, autoload не найден) | сообщение исключения; `exit(1)` |
| Конфиг вернул не `MigratorInterface` (старый стиль с `Console::run` внутри / `null`) | явное сообщение про требуемый `return $migrator;`; `exit(1)` |
| Ошибка миграции | обрабатывается существующим `Console`/`Migrator`; exit-код пробрасывается |
| Нет нужного pdo-расширения | не возникает — все три бандлятся в образ |

Сигналы (`SIGTERM` при `compose stop`): рекомендуем `init: true` в compose;
отмечается в README.

## 5. Тестирование

PHP-код библиотеки не меняется → существующие юнит-тесты не затрагиваются.

- **Smoke-тест bootstrap-логики** (изолированно, без Docker): PHP-тест, проверяющий
  резолв `MIGRATOR_CONFIG`, ошибку при отсутствии файла, ошибку при неверном
  return-контракте, успешный путь с фейковым конфигом, возвращающим
  `MigratorInterface`. Для тестируемости логика резолва/валидации вынесена в
  отдельную единицу.
- **Интеграционный CI-job**: собрать runtime-образ, поднять существующие
  `postgres`/`mysql` из `docker-compose.yml`, прогнать `migrate:init` и
  `migrate:up` с `example/docker/migrator.php`, проверить `exit 0`. Отдельный job,
  не блокирует основной `tests.yml`.
- **Makefile-таргет** `docker-runtime` для локальной сборки/прогона образа.

## 6. Структура изменений (файлы)

```
.docker/migrator/Dockerfile            (новый)
.docker/migrator/migrator              (новый, bootstrap)
src/cmd/...                            (без изменений)
.github/workflows/docker-publish.yml   (новый)
example/docker/migrator.php            (новый, пример конфига)
Makefile                               (+ таргет docker-runtime)
README.md                              (+ раздел Docker)
```

Решения по умолчанию: default-путь конфига = `/app/migrator.php`;
имя образа = `ghcr.io/dbschemix/migrator`; multi-arch `linux/amd64,linux/arm64`.

## Открытые вопросы

Нет. Все архитектурные развилки закрыты в ходе брейншторминга.
