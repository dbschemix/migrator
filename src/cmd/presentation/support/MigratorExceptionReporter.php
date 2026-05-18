<?php

declare(strict_types=1);

namespace dbschemix\migrator\cmd\presentation\support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use dbschemix\core\exception\InitializationException;

/**
 * Renders a recoverable migrator failure to the command output.
 *
 * The {@see InitializationException} hint text and the condition that
 * triggers it live here only, so the four commands that surface it
 * (migrate:up / down / redo / verify) cannot drift apart.
 */
trait MigratorExceptionReporter
{
    /**
     * @return Command::INVALID
     */
    private function reportRecoverableFailure(Throwable $e, OutputInterface $output): int
    {
        if ($e instanceof InitializationException) {
            $output->writeln('Calling the command "migrate:init" may help fix the error.');
        }

        $output->writeln($e->getMessage());

        return Command::INVALID;
    }
}
