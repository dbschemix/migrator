<?php

declare(strict_types=1);

namespace dbschemix\migrator\cmd;

use Exception;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Output\ConsoleOutput;
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

        $output = new ConsoleOutput();

        try {
            exit($console->run());
        } catch (Exception $e) {
            $output->writeln($e->getMessage());
            exit(Command::FAILURE);
        }
    }
}
