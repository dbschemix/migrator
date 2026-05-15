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
