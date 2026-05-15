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
use dbschemix\migrator\cmd\presentation\CreateCommand;
use dbschemix\migrator\tests\Fakes\FakeMigrator;
use dbschemix\migrator\tests\Fakes\FakeMigratorException;

#[CoversClass(CreateCommand::class)]
final class CreateCommandTest extends TestCase
{
    #[Test]
    public function success_path_calls_migrator_create_and_returns_success(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $command = new CreateCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute(['name' => 'create_users_table']);

        // Then
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($migrator->lastCreateOptions);
        self::assertSame('create_users_table', $migrator->lastCreateOptions->migrationName);
    }

    #[Test]
    public function migrator_exception_returns_invalid_and_writes_message(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->createException = new FakeMigratorException('create failed');
        $command = new CreateCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute(['name' => 'some_migration']);

        // Then
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('create failed', $tester->getDisplay());
    }

    #[Test]
    public function invalid_argument_exception_returns_invalid_and_writes_message(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->createException = new InvalidArgumentException('bad arg');
        $command = new CreateCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute(['name' => 'some_migration']);

        // Then
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('bad arg', $tester->getDisplay());
    }

    #[Test]
    public function generic_throwable_returns_failure(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->createException = new RuntimeException('unexpected');
        $command = new CreateCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute(['name' => 'some_migration']);

        // Then
        self::assertSame(Command::FAILURE, $exitCode);
    }

    #[Test]
    public function db_option_is_passed_through_to_migrator(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $command = new CreateCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([
            'name' => 'add_orders_table',
            '--db' => 'mydb',
        ]);

        // Then
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($migrator->lastCreateOptions);
        self::assertSame('mydb', $migrator->lastCreateOptions->dbName);
    }
}
