<?php

declare(strict_types=1);

namespace dbschemix\migrator\example\docker;

use dbschemix\pdo\Driver;
use dbschemix\core\Migration;
use dbschemix\core\Migrator;
use dbschemix\migrator\tools\PrettyConsoleOutput;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

/**
 * Container config contract: the file must `return` a MigratorInterface.
 * It is responsible for its own autoload (line above) and for resolving
 * paths via __DIR__ so they work inside the mounted container.
 */
return new Migrator(
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
