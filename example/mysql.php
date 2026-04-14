<?php

declare(strict_types=1);

namespace dbschemix\migrator\example;

use Throwable;
use dbschemix\pdo\Driver;
use dbschemix\core\InputOptions;
use dbschemix\core\Migration;
use dbschemix\core\Migrator;
use dbschemix\migrator\tools\PrettyConsoleOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

$migrator = new Migrator(
    list: [
        new Migration(
            path: __DIR__ . '/migration/mysql/main',
            driver: new Driver(
                dsn: 'mysql:host=mysql;dbname=main',
                username: 'dbuser',
                password: 'dbpassword',
            )
        )
    ],
    eventSubscribers: [
        new PrettyConsoleOutput(),
    ],
);

foreach (range(1, 1000) as $row) {
    $migrator->create(new InputOptions(dbName: "mysql/main", migrationName: $row . "-test"));
}

try {
    $migrator->init();
} catch (Throwable $exception) {
    echo $exception->getMessage() . PHP_EOL;
}

try {
    $migrator->up(new InputOptions(limit: 100));
} catch (Throwable $exception) {
    echo $exception->getMessage() . PHP_EOL;
}

try {
    $migrator->up();
} catch (Throwable $exception) {
    echo $exception->getMessage() . PHP_EOL;
}

try {
    $migrator->down();
} catch (Throwable $exception) {
    echo $exception->getMessage() . PHP_EOL;
}

$pattern = __DIR__ . '/migration/mysql/main/*test.sql';
/** @psalm-suppress RiskyTruthyFalsyComparison */
foreach (glob($pattern) ?: [] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
