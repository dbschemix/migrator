<?php

declare(strict_types=1);

namespace dbschemix\migrator\example;

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
