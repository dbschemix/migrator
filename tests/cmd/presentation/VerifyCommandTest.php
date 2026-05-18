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
use dbschemix\migrator\cmd\presentation\VerifyCommand;
use dbschemix\migrator\tests\Fakes\FakeMigrator;
use dbschemix\migrator\tests\Fakes\FakeMigratorException;

#[CoversClass(VerifyCommand::class)]
final class VerifyCommandTest extends TestCase
{
    #[Test]
    public function success_path_calls_migrator_verify_and_returns_success(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $command = new VerifyCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($migrator->lastVerifyOptions);
    }

    #[Test]
    public function migrator_exception_returns_invalid_and_writes_message(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->verifyException = new FakeMigratorException('verify failed');
        $command = new VerifyCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('verify failed', $tester->getDisplay());
    }

    #[Test]
    public function invalid_argument_exception_returns_invalid_and_writes_message(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->verifyException = new InvalidArgumentException('bad arg');
        $command = new VerifyCommand($migrator);
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
        $migrator->verifyException = new InitializationException('not initialized', new RuntimeException());
        $command = new VerifyCommand($migrator);
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
    public function unexpected_throwable_is_not_swallowed(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->verifyException = new RuntimeException('unexpected');
        $command = new VerifyCommand($migrator);
        $tester = new CommandTester($command);

        // Then
        $this->expectException(RuntimeException::class);

        // When
        $tester->execute([]);
    }
}
