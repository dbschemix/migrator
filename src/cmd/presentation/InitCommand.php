<?php

declare(strict_types=1);

namespace dbschemix\migrator\cmd\presentation;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use dbschemix\core\exception\MigratorException;
use dbschemix\core\MigratorInterface;

#[AsCommand(
    name: 'migrate:init',
    description: 'Initialization',
)]
final class InitCommand extends Command
{
    /**
     * @throws LogicException
     */
    public function __construct(private readonly MigratorInterface $migrator)
    {
        parent::__construct();
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->migrator->init();
        } catch (MigratorException $e) {
            $output->writeln($e->getMessage());
            return Command::INVALID;
        }

        // Unexpected throwables are not swallowed: they propagate to
        // Application::run(), which renders the exception type and message
        // (with a full stack trace under -v) and returns a non-zero exit code.
        return Command::SUCCESS;
    }
}
