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
use dbschemix\migrator\cmd\presentation\support\CommandOptions;
use dbschemix\migrator\cmd\presentation\support\MigratorExceptionReporter;

#[AsCommand(
    name: 'migrate:up',
    description: 'Up migration',
)]
final class UpCommand extends Command
{
    use CommandOptions;
    use MigratorExceptionReporter;

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
        $this->addOption(
            'exactly-all',
            null,
            InputOption::VALUE_NONE,
            'Ensures atomic execution of all migrations. ' .
            'If any migration fails, the entire batch is rolled back, leaving the database unchanged.'
        );
        $this->addOption(
            'with-repeatable',
            null,
            InputOption::VALUE_NONE,
            'Includes migrations from the repeatable directory in the execution. ' .
            'These scripts typically run every time their content changes, regardless of versioning.'
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->migrator->up($this->getOptions($input));
        } catch (InvalidArgumentException | MigratorException $e) {
            return $this->reportRecoverableFailure($e, $output);
        }

        // Unexpected throwables are not swallowed: they propagate to
        // Application::run(), which renders the exception type and message
        // (with a full stack trace under -v) and returns a non-zero exit code.
        return Command::SUCCESS;
    }
}
