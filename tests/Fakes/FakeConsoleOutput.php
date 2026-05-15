<?php

declare(strict_types=1);

namespace dbschemix\migrator\tests\Fakes;

use LogicException;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Hand-written fake that records writeln calls for assertion.
 */
final class FakeConsoleOutput implements ConsoleOutputInterface
{
    /** @var list<string> */
    public array $lines = [];

    /** @param string|iterable<string> $messages */
    public function writeln(string | iterable $messages, int $options = 0): void
    {
        if (is_iterable($messages)) {
            foreach ($messages as $message) {
                $this->lines[] = $message;
            }
        } else {
            $this->lines[] = $messages;
        }
    }

    /** @param string|iterable<string> $messages */
    public function write(string | iterable $messages, bool $newline = false, int $options = 0): void
    {
        // Not needed for our tests
    }

    public function getErrorOutput(): OutputInterface
    {
        return $this;
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        // Not needed for our tests
    }

    public function section(): ConsoleSectionOutput
    {
        throw new LogicException('Not implemented in fake');
    }

    public function setVerbosity(int $level): void
    {
        // Not needed for our tests
    }

    public function getVerbosity(): int
    {
        return self::VERBOSITY_NORMAL;
    }

    public function isQuiet(): bool
    {
        return false;
    }

    public function isVerbose(): bool
    {
        return false;
    }

    public function isVeryVerbose(): bool
    {
        return false;
    }

    public function isDebug(): bool
    {
        return false;
    }

    public function setDecorated(bool $decorated): void
    {
        // Not needed for our tests
    }

    public function isDecorated(): bool
    {
        return false;
    }

    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        // Not needed for our tests
    }

    public function getFormatter(): OutputFormatterInterface
    {
        throw new LogicException('Not implemented in fake');
    }
}
