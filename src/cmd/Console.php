<?php

declare(strict_types=1);

namespace dbschemix\migrator\cmd;

use Exception;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use dbschemix\migrator\cmd\presentation\CreateCommand;
use dbschemix\migrator\cmd\presentation\DownCommand;
use dbschemix\migrator\cmd\presentation\FixtureCommand;
use dbschemix\migrator\cmd\presentation\InitCommand;
use dbschemix\migrator\cmd\presentation\RedoCommand;
use dbschemix\migrator\cmd\presentation\UpCommand;
use dbschemix\migrator\cmd\presentation\VerifyCommand;
use dbschemix\core\MigratorInterface;

final readonly class Console
{
    /**
     * @throws Exception if Symfony Console fails to run the application
     */
    public static function run(MigratorInterface $migrator): never
    {
        $console = new Application();
        $console->setCommandLoader(
            new FactoryCommandLoader(
                [
                    'migrate:init' => static fn() => new InitCommand($migrator),
                    'migrate:up' => static fn() => new UpCommand($migrator),
                    'migrate:down' => static fn() => new DownCommand($migrator),
                    'migrate:redo' => static fn() => new RedoCommand($migrator),
                    'migrate:verify' => static fn() => new VerifyCommand($migrator),
                    'migrate:fixture' => static fn() => new FixtureCommand($migrator),
                    'migrate:create' => static fn() => new CreateCommand($migrator),
                ]
            )
        );

        // Application::run() catches uncaught throwables, renders them
        // (exception class + message, full stack trace under -v) to stderr,
        // and returns a non-zero exit code. We must not wrap it in our own
        // catch: doing so swallows the type and trace Symfony would render.
        exit($console->run());
    }
}
