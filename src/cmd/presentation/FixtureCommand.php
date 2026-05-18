<?php

declare(strict_types=1);

namespace dbschemix\migrator\cmd\presentation;

use Override;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use dbschemix\core\exception\MigratorException;
use dbschemix\core\MigratorInterface;

#[AsCommand(
    name: 'migrate:fixture',
    description: 'Fixture',
)]
final class FixtureCommand extends Command
{
    use CommandOptions;

    /**
     * @throws LogicException
     */
    public function __construct(private readonly MigratorInterface $migrator)
    {
        parent::__construct();
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Override]
    protected function configure(): void
    {
        $this->addOption('db', null, InputOption::VALUE_OPTIONAL, 'Name database');
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_OPTIONAL,
            'Sets the maximum number of migrations to be executed or rolled back.'
        );
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Simulates the migration process without applying any changes to the database.'
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->migrator->fixture($this->getOptions($input));
        } catch (InvalidArgumentException | MigratorException $e) {
            $output->writeln($e->getMessage());
            return Command::INVALID;
        }

        // Unexpected throwables are not swallowed: they propagate to
        // Application::run(), which renders the exception type and message
        // (with a full stack trace under -v) and returns a non-zero exit code.
        return Command::SUCCESS;
    }
}
