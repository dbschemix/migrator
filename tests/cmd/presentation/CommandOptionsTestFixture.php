<?php

declare(strict_types=1);

namespace dbschemix\migrator\tests\cmd\presentation;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use dbschemix\core\InputOptions;
use dbschemix\migrator\cmd\presentation\CommandOptions;

/**
 * Concrete fixture command that uses the CommandOptions trait and exposes
 * the captured InputOptions for assertion.
 */
#[AsCommand(name: 'test:command-options')]
final class CommandOptionsTestFixture extends Command
{
    use CommandOptions;

    public ?InputOptions $capturedOptions = null;

    /** @var callable(Command): void */
    private $configureCallback;

    /**
     * @param callable(Command): void $configureCallback
     */
    public function __construct(callable $configureCallback)
    {
        $this->configureCallback = $configureCallback;
        parent::__construct();
    }

    protected function configure(): void
    {
        ($this->configureCallback)($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->capturedOptions = $this->getOptions($input);
        return Command::SUCCESS;
    }
}
