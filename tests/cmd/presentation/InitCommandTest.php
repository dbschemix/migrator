<?php

declare(strict_types=1);

namespace dbschemix\migrator\tests\cmd\presentation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use dbschemix\migrator\cmd\presentation\InitCommand;
use dbschemix\migrator\tests\Fakes\FakeMigrator;
use dbschemix\migrator\tests\Fakes\FakeMigratorException;

#[CoversClass(InitCommand::class)]
final class InitCommandTest extends TestCase
{
    #[Test]
    public function success_path_calls_migrator_init_and_returns_success(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $command = new InitCommand($migrator);
        $tester = new CommandTester($command);

        // When — InitCommand uses __invoke; CommandTester still routes through execute()
        // which Symfony calls via __invoke when no execute() is defined
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(1, $migrator->initCallCount);
    }

    #[Test]
    public function migrator_exception_returns_invalid_and_writes_message(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->initException = new FakeMigratorException('init failed');
        $command = new InitCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('init failed', $tester->getDisplay());
    }

    #[Test]
    public function generic_throwable_returns_failure(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->initException = new RuntimeException('unexpected');
        $command = new InitCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::FAILURE, $exitCode);
    }
}
