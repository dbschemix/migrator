<?php

declare(strict_types=1);

namespace dbschemix\migrator\tests\cmd\presentation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;
use dbschemix\core\InputOptions;
use dbschemix\migrator\cmd\presentation\support\CommandOptions;

#[CoversTrait(CommandOptions::class)]
final class CommandOptionsTest extends TestCase
{
    // -----------------------------------------------------------------------
    // limit option
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('valid_limit_cases')]
    public function limit_option_maps_to_input_options(mixed $inputValue, int $expected): void
    {
        // Given
        $command = $this->makeCommandWithLimit();
        $tester = new CommandTester($command);

        // When
        $tester->execute($inputValue === null ? [] : ['--limit' => $inputValue]);

        // Then
        self::assertSame($expected, $this->capturedOptions($command)->limit);
    }

    /** @return array<string, array{mixed, int}> */
    public static function valid_limit_cases(): array
    {
        return [
            'happy path: no limit given uses default zero' => [null, 0],
            'happy path: explicit zero limit' => [0, 0],
            'happy path: positive integer limit' => [5, 5],
            'happy path: numeric string is cast to int' => ['3', 3],
        ];
    }

    #[Test]
    public function negative_limit_throws_invalid_argument_exception(): void
    {
        // Given
        $command = $this->makeCommandWithLimit();
        $tester = new CommandTester($command);

        // Then
        $this->expectException(InvalidArgumentException::class);

        // When — CommandTester re-throws exceptions by default
        $tester->execute(['--limit' => -1]);
    }

    #[Test]
    public function negative_limit_exception_message_contains_hint(): void
    {
        // Given
        $command = $this->makeCommandWithLimit();
        $tester = new CommandTester($command);

        // When
        try {
            $tester->execute(['--limit' => -1]);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            // Then — the plan says to assert message text only for the specific
            // documented string "Argument (limit) must be greater than to 0."
            self::assertSame('Argument (limit) must be greater than to 0.', $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // dry-run flag
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('dry_run_cases')]
    public function dry_run_flag_maps_to_input_options(bool $pass, bool $expected): void
    {
        // Given
        $command = $this->makeCommandWithDryRun();
        $tester = new CommandTester($command);

        // When
        $tester->execute($pass ? ['--dry-run' => true] : []);

        // Then
        self::assertSame($expected, $this->capturedOptions($command)->dryRun);
    }

    /** @return array<string, array{bool, bool}> */
    public static function dry_run_cases(): array
    {
        return [
            'happy path: flag passed -> dryRun true' => [true, true],
            'error: flag absent -> dryRun false' => [false, false],
        ];
    }

    // -----------------------------------------------------------------------
    // latest-version flag
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('latest_version_cases')]
    public function latest_version_flag_maps_to_apply_latest_version(bool $pass, bool $expected): void
    {
        // Given
        $command = $this->makeCommandWithLatestVersion();
        $tester = new CommandTester($command);

        // When
        $tester->execute($pass ? ['--latest-version' => true] : []);

        // Then
        // hasApplyLatestVersion() returns true when flag is set AND limit==0 AND version==0
        self::assertSame($expected, $this->capturedOptions($command)->hasApplyLatestVersion());
    }

    /** @return array<string, array{bool, bool}> */
    public static function latest_version_cases(): array
    {
        return [
            'happy path: flag passed -> hasApplyLatestVersion true' => [true, true],
            'error: flag absent -> hasApplyLatestVersion false' => [false, false],
        ];
    }

    // -----------------------------------------------------------------------
    // exactly-all flag
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('exactly_all_cases')]
    public function exactly_all_flag_maps_to_input_options(bool $pass, bool $expected): void
    {
        // Given
        $command = $this->makeCommandWithExactlyAll();
        $tester = new CommandTester($command);

        // When
        $tester->execute($pass ? ['--exactly-all' => true] : []);

        // Then
        self::assertSame($expected, $this->capturedOptions($command)->exactlyAll);
    }

    /** @return array<string, array{bool, bool}> */
    public static function exactly_all_cases(): array
    {
        return [
            'happy path: flag passed -> exactlyAll true' => [true, true],
            'error: flag absent -> exactlyAll false' => [false, false],
        ];
    }

    // -----------------------------------------------------------------------
    // with-repeatable flag
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('with_repeatable_cases')]
    public function with_repeatable_flag_maps_to_has_repeatable(bool $pass, bool $expected): void
    {
        // Given
        $command = $this->makeCommandWithRepeatable();
        $tester = new CommandTester($command);

        // When
        $tester->execute($pass ? ['--with-repeatable' => true] : []);

        // Then
        // hasRepeatable() requires dryRun===false (default) AND hasRepeatable===true
        self::assertSame($expected, $this->capturedOptions($command)->hasRepeatable());
    }

    /** @return array<string, array{bool, bool}> */
    public static function with_repeatable_cases(): array
    {
        return [
            'happy path: flag passed -> hasRepeatable true' => [true, true],
            'error: flag absent -> hasRepeatable false' => [false, false],
        ];
    }

    // -----------------------------------------------------------------------
    // db option
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('db_option_cases')]
    public function db_option_maps_to_db_name(mixed $inputValue, ?string $expected): void
    {
        // Given
        $command = $this->makeCommandWithDb();
        $tester = new CommandTester($command);

        // When
        $tester->execute($inputValue === null ? [] : ['--db' => $inputValue]);

        // Then
        self::assertSame($expected, $this->capturedOptions($command)->dbName);
    }

    /** @return array<string, array{mixed, ?string}> */
    public static function db_option_cases(): array
    {
        return [
            'happy path: non-empty string is returned' => ['mydb', 'mydb'],
            'error: empty string becomes null' => ['', null],
            'error: option absent becomes null' => [null, null],
        ];
    }

    // -----------------------------------------------------------------------
    // name argument
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('name_argument_cases')]
    public function name_argument_maps_to_migration_name(string $inputValue, ?string $expected): void
    {
        // Given
        $command = $this->makeCommandWithName();
        $tester = new CommandTester($command);

        // When
        $tester->execute(['name' => $inputValue]);

        // Then
        self::assertSame($expected, $this->capturedOptions($command)->migrationName);
    }

    /** @return array<string, array{string, ?string}> */
    public static function name_argument_cases(): array
    {
        return [
            'happy path: non-empty name is returned' => ['my_migration', 'my_migration'],
            'error: empty string becomes null' => ['', null],
        ];
    }

    // -----------------------------------------------------------------------
    // hasOption/hasArgument guard: unregistered options have defaults
    // -----------------------------------------------------------------------

    #[Test]
    public function command_without_limit_option_produces_default_limit_zero(): void
    {
        // Given — a command that registers NO options at all
        $command = $this->makeMinimalCommand();
        $tester = new CommandTester($command);

        // When
        $tester->execute([]);

        // Then — limit stays at the InputOptions default of 0
        self::assertSame(0, $this->capturedOptions($command)->limit);
    }

    #[Test]
    public function command_without_db_option_produces_null_db_name(): void
    {
        // Given
        $command = $this->makeMinimalCommand();
        $tester = new CommandTester($command);

        // When
        $tester->execute([]);

        // Then
        self::assertNull($this->capturedOptions($command)->dbName);
    }

    #[Test]
    public function command_without_dry_run_option_produces_dry_run_false(): void
    {
        // Given
        $command = $this->makeMinimalCommand();
        $tester = new CommandTester($command);

        // When
        $tester->execute([]);

        // Then
        self::assertFalse($this->capturedOptions($command)->dryRun);
    }

    // -----------------------------------------------------------------------
    // Factory helpers that build minimal concrete test Commands
    // -----------------------------------------------------------------------

    private function capturedOptions(CommandOptionsTestFixture $command): InputOptions
    {
        self::assertNotNull(
            $command->capturedOptions,
            'execute() must have run and captured InputOptions before assertion',
        );

        return $command->capturedOptions;
    }

    private function makeMinimalCommand(): CommandOptionsTestFixture
    {
        return new CommandOptionsTestFixture(
            configureCallback: static function (Command $cmd): void {
                // No options registered — tests hasOption/hasArgument guards
            }
        );
    }

    private function makeCommandWithLimit(): CommandOptionsTestFixture
    {
        return new CommandOptionsTestFixture(
            configureCallback: static function (Command $cmd): void {
                $cmd->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit');
            }
        );
    }

    private function makeCommandWithDryRun(): CommandOptionsTestFixture
    {
        return new CommandOptionsTestFixture(
            configureCallback: static function (Command $cmd): void {
                $cmd->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run');
            }
        );
    }

    private function makeCommandWithLatestVersion(): CommandOptionsTestFixture
    {
        return new CommandOptionsTestFixture(
            configureCallback: static function (Command $cmd): void {
                $cmd->addOption('latest-version', null, InputOption::VALUE_NONE, 'Latest version');
            }
        );
    }

    private function makeCommandWithExactlyAll(): CommandOptionsTestFixture
    {
        return new CommandOptionsTestFixture(
            configureCallback: static function (Command $cmd): void {
                $cmd->addOption('exactly-all', null, InputOption::VALUE_NONE, 'Exactly all');
            }
        );
    }

    private function makeCommandWithRepeatable(): CommandOptionsTestFixture
    {
        return new CommandOptionsTestFixture(
            configureCallback: static function (Command $cmd): void {
                $cmd->addOption('with-repeatable', null, InputOption::VALUE_NONE, 'With repeatable');
            }
        );
    }

    private function makeCommandWithDb(): CommandOptionsTestFixture
    {
        return new CommandOptionsTestFixture(
            configureCallback: static function (Command $cmd): void {
                $cmd->addOption('db', null, InputOption::VALUE_OPTIONAL, 'DB name');
            }
        );
    }

    private function makeCommandWithName(): CommandOptionsTestFixture
    {
        return new CommandOptionsTestFixture(
            configureCallback: static function (Command $cmd): void {
                $cmd->addArgument('name', InputArgument::OPTIONAL, 'Migration name');
            }
        );
    }
}
