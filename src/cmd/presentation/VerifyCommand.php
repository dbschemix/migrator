<?php

declare(strict_types=1);

namespace dbschemix\migrator\cmd\presentation;

use Override;
use Throwable;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use dbschemix\core\exception\InitializationException;
use dbschemix\core\exception\MigratorException;
use dbschemix\core\MigratorInterface;

#[AsCommand(
    name: 'migrate:verify',
    description: 'Up and Down migration',
)]
final class VerifyCommand extends Command
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
            $this->migrator->verify($this->getOptions($input));
        } catch (InvalidArgumentException | MigratorException $e) {
            if ($e instanceof InitializationException) {
                $output->writeln('Calling the command "migrate:init" may help fix the error.');
            }
            $output->writeln($e->getMessage());
            return Command::INVALID;
        } catch (Throwable) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
