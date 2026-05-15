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
