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
            path: __DIR__ . '/migration/postgres/main',
            driver: new Driver(
                dsn: 'pgsql:host=postgres;port=5432;dbname=main',
                username: 'postgres',
                password: 'postgres',
            )
        ),
        // database copy, single data source migrations
        new Migration(
            path: __DIR__ . '/migration/postgres/main',
            driver: new Driver(
                dsn: 'pgsql:host=postgres;port=5432;dbname=maincopy',
                username: 'postgres',
                password: 'postgres',
            )
        ),
    ],
    eventSubscribers: [
        new PrettyConsoleOutput(),
    ],
);

Console::run($migrator);
