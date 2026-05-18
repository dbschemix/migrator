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
use dbschemix\migrator\cmd\presentation\FixtureCommand;
use dbschemix\migrator\tests\Fakes\FakeMigrator;
use dbschemix\migrator\tests\Fakes\FakeMigratorException;

#[CoversClass(FixtureCommand::class)]
final class FixtureCommandTest extends TestCase
{
    #[Test]
    public function success_path_calls_migrator_fixture_and_returns_success(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $command = new FixtureCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($migrator->lastFixtureOptions);
    }

    #[Test]
    public function migrator_exception_returns_invalid_and_writes_message(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->fixtureException = new FakeMigratorException('fixture failed');
        $command = new FixtureCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('fixture failed', $tester->getDisplay());
    }

    #[Test]
    public function invalid_argument_exception_returns_invalid_and_writes_message(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->fixtureException = new InvalidArgumentException('bad arg');
        $command = new FixtureCommand($migrator);
        $tester = new CommandTester($command);

        // When
        $exitCode = $tester->execute([]);

        // Then
        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('bad arg', $tester->getDisplay());
    }

    #[Test]
    public function unexpected_throwable_is_not_swallowed_and_propagates(): void
    {
        // Given
        $migrator = new FakeMigrator();
        $migrator->fixtureException = new RuntimeException('unexpected');
        $command = new FixtureCommand($migrator);
        $tester = new CommandTester($command);

        // Then — the unexpected error must stay visible (type + message),
        // not be turned into a silent Command::FAILURE. It propagates so
        // Symfony's Application renderer reports it on stderr.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unexpected');

        // When
        $tester->execute([]);
    }
}
