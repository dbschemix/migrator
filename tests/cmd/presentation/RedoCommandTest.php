<?php

declare(strict_types=1);

namespace dbschemix\migrator\tests\cmd\presentation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use dbschemix\core\exception\InitializationException;
use dbschemix\migrator\cmd\presentation\RedoCommand;
use dbschemix\migrator\tests\Fakes\FakeMigrator;
use dbschemix\migrator\tests\Fakes\FakeMigratorException;

#[CoversClass(RedoCommand::class)]
final class RedoCommandTest extends TestCase
{
    #[Test]
    public function success_path_calls_migrator_redo_and_returns_success(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $command = new RedoCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($migrator->lastRedoOptions);
    }

    #[Test]
    public function migrator_exception_returns_invalid_and_writes_message(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->redoException = new FakeMigratorException('redo failed');
        $command = new RedoCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('redo failed', $tester->getDisplay());
    }

    #[Test]
    public function invalid_argument_exception_returns_invalid_and_writes_message(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->redoException = new InvalidArgumentException('bad arg');
        $command = new RedoCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('bad arg', $tester->getDisplay());
    }

    #[Test]
    public function initialization_exception_writes_hint_and_returns_invalid(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->redoException = new InitializationException('not initialized', new RuntimeException());
        $command = new RedoCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString(
            'Calling the command "migrate:init" may help fix the error.',
            $tester->getDisplay()
        );
    }

    #[Test]
    public function generic_throwable_returns_failure(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->redoException = new RuntimeException('unexpected');
        $command = new RedoCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::FAILURE, $exitCode);
    }
}
