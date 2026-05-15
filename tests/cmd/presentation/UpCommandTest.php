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
use dbschemix\migrator\cmd\presentation\UpCommand;
use dbschemix\migrator\tests\Fakes\FakeMigrator;
use dbschemix\migrator\tests\Fakes\FakeMigratorException;

#[CoversClass(UpCommand::class)]
final class UpCommandTest extends TestCase
{
    #[Test]
    public function success_path_calls_migrator_up_and_returns_success(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $command = new UpCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($migrator->lastUpOptions);
    }

    #[Test]
    public function migrator_exception_returns_invalid_and_writes_message(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->upException = new FakeMigratorException('something went wrong');
        $command = new UpCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('something went wrong', $tester->getDisplay());
    }

    #[Test]
    public function invalid_argument_exception_returns_invalid_and_writes_message(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->upException = new InvalidArgumentException('bad argument');
        $command = new UpCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('bad argument', $tester->getDisplay());
    }

    #[Test]
    public function initialization_exception_writes_hint_and_returns_invalid(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->upException = new InitializationException('not initialized', new RuntimeException());
        $command = new UpCommand($migrator);
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
        $migrator->upException = new RuntimeException('unexpected error');
        $command = new UpCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::FAILURE, $exitCode);
    }
}
