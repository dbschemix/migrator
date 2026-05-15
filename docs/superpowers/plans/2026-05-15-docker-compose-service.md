# Docker-compose Service Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship `dbschemix/migrator` as a thin, publishable ghcr runtime image that runs migration commands from a user's PHP config (`return $migrator;`) when wired into docker-compose via `image:` + `command:`.

**Architecture:** A standalone PHP bootstrap shim is `ENTRYPOINT` in a thin runtime image (PHP CLI + pdo extensions, no library). The shim resolves the config path, `require`s the user's config (which registers the user's autoload, including the library from their `vendor/`), validates the returned value through a small testable library class `dbschemix\migrator\cmd\Bootstrap`, then delegates to the existing `Console::run()`. Docker `command:` arrives as `$argv` and is read by Symfony Console.

**Tech Stack:** PHP 8.3, Symfony Console (existing), PHPUnit 12, Docker, GitHub Actions (docker/build-push-action), ghcr.io.

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `src/cmd/Bootstrap.php` | Pure, testable validation of the config return value (`assertMigrator`) | Create |
| `tests/cmd/BootstrapTest.php` | Unit tests for `Bootstrap::assertMigrator` (both branches) | Create |
| `.docker/migrator/migrator` | Standalone entrypoint shim: resolve path → require config → validate → run Console (infra, not statically analyzed; no `.php` extension) | Create |
| `.docker/migrator/Dockerfile` | Thin runtime image definition | Create |
| `example/docker/migrator.php` | Example config in the new contract (sqlite, `return $migrator;`) | Create |
| `.github/workflows/docker-publish.yml` | Build & push multi-arch image to ghcr on release | Create |
| `.github/workflows/docker-runtime.yml` | PR integration job: build image, run `migrate:init`/`migrate:up` against the sqlite example, assert exit 0 | Create |
| `Makefile` | `docker-runtime` target for local build/run | Modify |
| `README.md` | New "Docker / docker-compose" section | Modify |

**Design note vs. spec:** the spec said the library PHP code "скорее всего не требует изменений". This plan adds **one small additive class** `src/cmd/Bootstrap.php` — this is exactly the "маленькая тестируемая единица" the spec §2.1 calls for. `Console` is unchanged. The path-resolution and file-readable checks stay inline in the shim (trivial, exercised by the integration job) because they must run *before* any autoload exists in the thin runtime.

---

## Task 1: `Bootstrap::assertMigrator` (library, TDD)

**Files:**
- Create: `src/cmd/Bootstrap.php`
- Test: `tests/cmd/BootstrapTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/cmd/BootstrapTest.php`:

```php
<?php

declare(strict_types=1);

namespace dbschemix\migrator\tests\cmd;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use dbschemix\migrator\cmd\Bootstrap;
use dbschemix\migrator\tests\Fakes\FakeMigrator;

#[CoversClass(Bootstrap::class)]
final class BootstrapTest extends TestCase
{
    #[Test]
    public function assert_migrator_returns_same_instance_when_migrator_interface(): void
    {
        // Given
        $migrator = new FakeMigrator();

        // When
        $result = Bootstrap::assertMigrator($migrator);

        // Then
        self::assertSame($migrator, $result);
    }

    #[Test]
    public function assert_migrator_throws_when_config_returned_null(): void
    {
        // Given / When / Then
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('return $migrator');

        Bootstrap::assertMigrator(null);
    }

    #[Test]
    public function assert_migrator_throws_when_config_returned_other_object(): void
    {
        // Given / When / Then
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MigratorInterface');

        Bootstrap::assertMigrator(new stdClass());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter BootstrapTest`
Expected: FAIL — `Class "dbschemix\migrator\cmd\Bootstrap" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `src/cmd/Bootstrap.php`:

```php
<?php

declare(strict_types=1);

namespace dbschemix\migrator\cmd;

use RuntimeException;
use dbschemix\core\MigratorInterface;

/**
 * Validates the value returned by a user's migrator config file.
 *
 * Kept tiny and dependency-free on purpose: it is the only unit-testable
 * piece of the container entrypoint. Path resolution and the require()
 * itself live in the standalone shim because they must run before any
 * autoload exists in the thin runtime image.
 *
 * @api
 */
final class Bootstrap
{
    /**
     * @throws RuntimeException if the config did not return a MigratorInterface
     */
    public static function assertMigrator(mixed $config): MigratorInterface
    {
        if ($config instanceof MigratorInterface) {
            return $config;
        }

        throw new RuntimeException(
            'config must end with "return $migrator;" and return an instance of '
            . MigratorInterface::class . '.'
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter BootstrapTest`
Expected: PASS (3 tests, 3 assertions).

- [ ] **Step 5: Run static analysis on the new code**

Run: `composer check`
Expected: phpcs, psalm, phpstan all pass with no new errors.

- [ ] **Step 6: Commit**

```bash
git add src/cmd/Bootstrap.php tests/cmd/BootstrapTest.php
git commit -m "feat: add Bootstrap::assertMigrator config-contract guard"
```

---

## Task 2: Standalone entrypoint shim

**Files:**
- Create: `.docker/migrator/migrator`

- [ ] **Step 1: Create the shim**

Create `.docker/migrator/migrator` (no extension; this is `/usr/local/bin/migrator` in the image and is intentionally outside statically-analyzed paths):

```php
<?php

declare(strict_types=1);

/**
 * Container entrypoint for dbschemix/migrator.
 *
 * Flow: resolve config path -> require user's config (it registers its own
 * autoload, incl. the library from the user's vendor/) -> validate the
 * returned Migrator -> hand argv-driven control to Console.
 */

$configPath = getenv('MIGRATOR_CONFIG');
if ($configPath === false || $configPath === '') {
    $configPath = '/app/migrator.php';
}

if (!is_file($configPath) || !is_readable($configPath)) {
    fwrite(
        STDERR,
        "migrator: config not found or not readable: {$configPath}\n"
        . "Set MIGRATOR_CONFIG or mount your project so the file is reachable.\n"
    );
    exit(1);
}

try {
    /** @psalm-suppress UnresolvableInclude */
    $config = require $configPath;
} catch (\Throwable $e) {
    fwrite(STDERR, 'migrator: failed to load config: ' . $e->getMessage() . "\n");
    exit(1);
}

try {
    $migrator = \dbschemix\migrator\cmd\Bootstrap::assertMigrator($config);
} catch (\Throwable $e) {
    fwrite(STDERR, 'migrator: ' . $e->getMessage() . "\n");
    exit(1);
}

\dbschemix\migrator\cmd\Console::run($migrator);
```

- [ ] **Step 2: Syntax-check the shim**

Run: `php -l .docker/migrator/migrator`
Expected: `No syntax errors detected in .docker/migrator/migrator`

- [ ] **Step 3: Commit**

```bash
git add .docker/migrator/migrator
git commit -m "feat: add container entrypoint shim"
```

---

## Task 3: Runtime Dockerfile

**Files:**
- Create: `.docker/migrator/Dockerfile`

- [ ] **Step 1: Create the Dockerfile**

Create `.docker/migrator/Dockerfile` (build context is `.docker/migrator`, mirroring the existing `postgresql`/`mysql` services, so the repo-root `.dockerignore` is irrelevant):

```dockerfile
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
```

- [ ] **Step 2: Build the image**

Run: `docker build -t migrator-runtime:dev .docker/migrator`
Expected: build succeeds; final line `naming to docker.io/library/migrator-runtime:dev`.

- [ ] **Step 3: Verify entrypoint fails cleanly with no config**

Run: `docker run --rm migrator-runtime:dev migrate:up`
Expected: stderr `migrator: config not found or not readable: /app/migrator.php` and exit code `1`.

Verify exit code: `docker run --rm migrator-runtime:dev migrate:up; echo $?`
Expected: prints `1`.

- [ ] **Step 4: Commit**

```bash
git add .docker/migrator/Dockerfile
git commit -m "feat: add thin runtime Dockerfile"
```

---

## Task 4: Example config in the new contract

**Files:**
- Create: `example/docker/migrator.php`

- [ ] **Step 1: Confirm the sqlite example assets exist**

Run: `ls example/migration/sqlite/memory && ls -d example/data/sqlite`
Expected: lists the sqlite migration files and the `example/data/sqlite` directory (used by the existing `example/cli.php`).

- [ ] **Step 2: Create the example config**

Create `example/docker/migrator.php` (file lives at `/app/example/docker/migrator.php` inside the container; project root is two levels up):

```php
<?php

declare(strict_types=1);

namespace dbschemix\migrator\example\docker;

use dbschemix\pdo\Driver;
use dbschemix\core\Migration;
use dbschemix\core\Migrator;
use dbschemix\core\MigratorInterface;
use dbschemix\migrator\tools\PrettyConsoleOutput;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/**
 * Container config contract: the file must `return` a MigratorInterface.
 * It is responsible for its own autoload (line above) and for resolving
 * paths via __DIR__ so they work inside the mounted container.
 */
$migrator = new Migrator(
    list: [
        new Migration(
            path: dirname(__DIR__) . '/migration/sqlite/memory',
            driver: new Driver(
                dsn: 'sqlite:' . dirname(__DIR__) . '/data/sqlite/db.sqlite3',
            ),
        ),
    ],
    eventSubscribers: [
        new PrettyConsoleOutput(),
    ],
);

return $migrator;

/** @var MigratorInterface $migrator phpstan: documents the contract */
```

> Note: the trailing `@var` line is a no-op comment after `return`; if `composer check` (phpstan over `example/`) complains about unreachable/dead code, delete that final comment line — it is only documentation. Keep the explicit `MigratorInterface` import so the contract is greppable.

- [ ] **Step 3: Syntax + static analysis**

Run: `php -l example/docker/migrator.php && composer check`
Expected: no syntax errors; `composer check` passes (phpstan analyzes `example/`). If phpstan flags the trailing `@var` comment line, remove it and re-run until green.

- [ ] **Step 4: Run the example through the runtime image**

Run:
```bash
docker run --rm -u "$(id -u)" -e MIGRATOR_CONFIG=/app/example/docker/migrator.php \
  -v "$PWD:/app" migrator-runtime:dev migrate:init
docker run --rm -u "$(id -u)" -e MIGRATOR_CONFIG=/app/example/docker/migrator.php \
  -v "$PWD:/app" migrator-runtime:dev migrate:up
```
Expected: `migrate:init` prints initialization output; `migrate:up` prints `[sqlite/db] up: ...` lines; both exit `0`.

- [ ] **Step 5: Clean up generated sqlite state**

Run: `git status --porcelain example/data` and remove any generated `db.sqlite3` so it is not committed:
```bash
git checkout -- example/data 2>/dev/null || true
git clean -fd example/data
```
Expected: `example/data` back to its committed state.

- [ ] **Step 6: Commit**

```bash
git add example/docker/migrator.php
git commit -m "feat: add docker example config (return \$migrator contract)"
```

---

## Task 5: Makefile `docker-runtime` target

**Files:**
- Modify: `Makefile` (add target under the `## Application` section, before `stop:`)

- [ ] **Step 1: Add the target**

In `Makefile`, immediately after the `example:` target block and before `stop: ## Stop server`, insert:

```makefile
docker-runtime: ## Build runtime image and run the sqlite example through it
	docker build -t migrator-runtime:dev .docker/migrator
	docker run --rm -u $(USER) -e MIGRATOR_CONFIG=/app/example/docker/migrator.php \
		-v "$$(pwd):/app" migrator-runtime:dev migrate:init
	docker run --rm -u $(USER) -e MIGRATOR_CONFIG=/app/example/docker/migrator.php \
		-v "$$(pwd):/app" migrator-runtime:dev migrate:up
	git clean -fd example/data

```

(`USER = $$(id -u)` is already defined at the top of the Makefile.)

- [ ] **Step 2: Verify the target is listed and runs**

Run: `make help | grep docker-runtime`
Expected: shows `docker-runtime   Build runtime image and run the sqlite example through it`.

Run: `make docker-runtime`
Expected: image builds; `migrate:init` and `migrate:up` succeed; ends cleanly.

- [ ] **Step 3: Commit**

```bash
git add Makefile
git commit -m "chore: add docker-runtime make target"
```

---

## Task 6: GitHub Actions — publish to ghcr

**Files:**
- Create: `.github/workflows/docker-publish.yml`

- [ ] **Step 1: Create the workflow**

Create `.github/workflows/docker-publish.yml`:

```yaml
name: Docker Publish

on:
  release:
    types: [ published ]

jobs:
  image:
    name: Build and push image
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Set up QEMU
        uses: docker/setup-qemu-action@v3

      - name: Set up Buildx
        uses: docker/setup-buildx-action@v3

      - name: Log in to GitHub Container Registry
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Docker metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ghcr.io/dbschemix/migrator
          tags: |
            type=semver,pattern={{version}}
            type=semver,pattern={{major}}.{{minor}}
            type=semver,pattern={{major}}
            type=raw,value=latest

      - name: Build and push
        uses: docker/build-push-action@v6
        with:
          context: .docker/migrator
          platforms: linux/amd64,linux/arm64
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
```

- [ ] **Step 2: Validate workflow YAML**

Run: `python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/docker-publish.yml')); print('ok')"`
Expected: prints `ok`.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/docker-publish.yml
git commit -m "ci: publish runtime image to ghcr on release"
```

---

## Task 7: GitHub Actions — PR integration job

**Files:**
- Create: `.github/workflows/docker-runtime.yml`

- [ ] **Step 1: Create the workflow**

Create `.github/workflows/docker-runtime.yml`:

```yaml
name: Docker Runtime

on: [ pull_request ]

jobs:
  runtime:
    name: runtime image smoke test
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"
          extensions: pdo pdo_sqlite
        env:
          COMPOSER_TOKEN: ${{ secrets.GITHUB_TOKEN }}

      - name: Composer install dependencies
        uses: ramsey/composer-install@v3
        with:
          dependency-versions: "highest"
          composer-options: "--optimize-autoloader"

      - name: Build runtime image
        run: docker build -t migrator-runtime:ci .docker/migrator

      - name: migrate:init through the image
        run: |
          docker run --rm -u "$(id -u)" \
            -e MIGRATOR_CONFIG=/app/example/docker/migrator.php \
            -v "$PWD:/app" migrator-runtime:ci migrate:init

      - name: migrate:up through the image
        run: |
          docker run --rm -u "$(id -u)" \
            -e MIGRATOR_CONFIG=/app/example/docker/migrator.php \
            -v "$PWD:/app" migrator-runtime:ci migrate:up

      - name: Bad config contract fails with exit 1
        run: |
          set +e
          docker run --rm -u "$(id -u)" \
            -e MIGRATOR_CONFIG=/app/composer.json \
            -v "$PWD:/app" migrator-runtime:ci migrate:up
          code=$?
          set -e
          test "$code" -eq 1
```

- [ ] **Step 2: Validate workflow YAML**

Run: `python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/docker-runtime.yml')); print('ok')"`
Expected: prints `ok`.

- [ ] **Step 3: Locally reproduce the integration steps**

Run:
```bash
docker build -t migrator-runtime:ci .docker/migrator
docker run --rm -u "$(id -u)" -e MIGRATOR_CONFIG=/app/example/docker/migrator.php -v "$PWD:/app" migrator-runtime:ci migrate:init
docker run --rm -u "$(id -u)" -e MIGRATOR_CONFIG=/app/example/docker/migrator.php -v "$PWD:/app" migrator-runtime:ci migrate:up
docker run --rm -u "$(id -u)" -e MIGRATOR_CONFIG=/app/composer.json -v "$PWD:/app" migrator-runtime:ci migrate:up; echo "exit=$?"
git clean -fd example/data
```
Expected: init/up succeed; the `composer.json` run prints `migrator: config must end with "return $migrator;"...` and `exit=1`.

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/docker-runtime.yml
git commit -m "ci: smoke-test runtime image on pull requests"
```

---

## Task 8: README documentation

**Files:**
- Modify: `README.md` (append a new section after the existing `### Example` block, end of file)

- [ ] **Step 1: Append the Docker section**

Append to the end of `README.md`:

```markdown

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
```

- [ ] **Step 2: Verify markdown renders sanely**

Run: `tail -n 70 README.md`
Expected: the new section is present and the fenced code blocks are balanced (no stray ``` ).

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: document docker-compose usage"
```

---

## Task 9: Final full verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full quality gate**

Run: `composer check && vendor/bin/phpunit`
Expected: phpcs, psalm, phpstan all green; full PHPUnit suite passes (including `BootstrapTest` and all pre-existing tests).

- [ ] **Step 2: Confirm working tree is clean of generated artifacts**

Run: `git status --porcelain`
Expected: empty output (the sqlite `example/data` state was cleaned in Tasks 4/7).

- [ ] **Step 3: Confirm the branch history**

Run: `git log --oneline main..HEAD`
Expected: the per-task commits from Tasks 1–8 plus the design-spec commit, in order.

---

## Self-Review

**Spec coverage:**
- Spec §1 Architecture (resolve → require → validate → Console, argv) → Tasks 1, 2.
- Spec §2.1 Bootstrap shim + testable unit → Tasks 1 (`Bootstrap`), 2 (shim).
- Spec §2.2 Dockerfile (base, pdo extensions, COPY, non-root, ENV, ENTRYPOINT) → Task 3.
- Spec §2.3 CI publish to ghcr (release trigger, permissions, metadata tags, multi-arch) → Task 6.
- Spec §2.4 Example integration (sqlite config, compose snippet) → Tasks 4, 8.
- Spec §2.5 README section → Task 8.
- Spec §4 Error handling (missing/unreadable config, throwing require, wrong contract, exit codes) → shim in Task 2, `Bootstrap` in Task 1, asserted in Tasks 3 & 7.
- Spec §5 Testing (isolated bootstrap unit test, integration CI job, Makefile target) → Tasks 1, 7, 5.
- Spec §6 File structure → matches the File Structure table above.

**Placeholder scan:** No TBD/TODO/"handle edge cases"; every code step contains full content; the only conditional ("if phpstan flags the trailing comment, delete it") has an explicit, deterministic action.

**Type consistency:** `Bootstrap::assertMigrator(mixed): MigratorInterface` is defined once (Task 1) and referenced with that exact signature by the shim (Task 2). `MIGRATOR_CONFIG` default `/app/migrator.php` is identical across shim (Task 2), Dockerfile (Task 3), README (Task 8). Image name `ghcr.io/dbschemix/migrator` consistent (Tasks 6, 8). Build context `.docker/migrator` consistent (Tasks 3, 5, 6, 7).

No gaps found.
