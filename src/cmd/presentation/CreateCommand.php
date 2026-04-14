<?php

declare(strict_types=1);

namespace dbschemix\migrator\cmd\presentation;

use Override;
use Throwable;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use dbschemix\core\exception\MigratorException;
use dbschemix\core\MigratorInterface;

#[AsCommand(
    name: 'migrate:create',
    description: 'Create migration',
)]
final class CreateCommand extends Command
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
        $this->addOption('db', null, InputOption::VALUE_REQUIRED, 'The name database');
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the migration');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->migrator->create($this->getOptions($input));
        } catch (InvalidArgumentException | MigratorException $e) {
            $output->writeln($e->getMessage());
            return Command::INVALID;
        } catch (Throwable) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
