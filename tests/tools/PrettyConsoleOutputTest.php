<?php

declare(strict_types=1);

namespace dbschemix\migrator\tests\tools;

use Closure;
use League\CLImate\CLImate;
use League\CLImate\Util\Writer\Buffer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use dbschemix\core\Context;
use dbschemix\core\event\Event;
use dbschemix\core\event\MigrateErrorEvent;
use dbschemix\core\event\MigrateSuccessEvent;
use dbschemix\core\event\ExceptionEvent;
use dbschemix\core\event\Subscription;
use dbschemix\migrator\tools\PrettyConsoleOutput;
use RuntimeException;

#[CoversClass(PrettyConsoleOutput::class)]
final class PrettyConsoleOutputTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helper: build CLImate wired to an in-memory buffer
    // -----------------------------------------------------------------------

    /**
     * Returns a CLImate instance redirected to its built-in buffer writer,
     * plus a callable that reads accumulated output from that buffer.
     *
     * @return array{CLImate, callable(): string}
     */
    private function makeBufferedClimate(): array
    {
        $climate = new CLImate();
        $climate->output->defaultTo('buffer');

        /** @var Buffer $buffer */
        $buffer = $climate->output->get('buffer');

        return [$climate, $buffer->get(...)];
    }

    /**
     * @param list<Subscription> $subscriptions
     * @return Closure(Event, \dbschemix\core\event\EventInterface): void
     */
    private static function callbackFor(array $subscriptions, Event $event): Closure
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription->event === $event) {
                return $subscription->callback;
            }
        }

        self::fail("No subscription registered for event {$event->value}");
    }

    // -----------------------------------------------------------------------
    // subscriptions()
    // -----------------------------------------------------------------------

    #[Test]
    public function subscriptions_covers_every_event_case(): void
    {
        // Given
        $pretty = new PrettyConsoleOutput(new CLImate());

        // When
        $subscriptions = $pretty->subscriptions();

        // Then
        $expected = Event::cases();
        sort($expected);

        $actual = array_map(static fn(Subscription $s): Event => $s->event, $subscriptions);
        sort($actual);

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function subscriptions_maps_migrate_success_to_success_handler(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $context = new Context(dbName: 'db', filename: 'V1.sql', query: 'SELECT 1');
        $event = new MigrateSuccessEvent(action: 'up', context: $context);

        // When
        $subscriptions = $pretty->subscriptions();
        (self::callbackFor($subscriptions, Event::MigrateSuccess))(Event::MigrateSuccess, $event);

        // Then
        self::assertStringContainsString('db', $readBuffer());
    }

    #[Test]
    public function subscriptions_maps_migrate_error_to_error_handler(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $context = new Context(dbName: 'db', filename: 'V1.sql', query: 'SELECT 1');
        $event = new MigrateErrorEvent(
            action: 'up',
            context: $context,
            exception: new RuntimeException('some error'),
        );

        // When
        $subscriptions = $pretty->subscriptions();
        (self::callbackFor($subscriptions, Event::MigrateError))(Event::MigrateError, $event);

        // Then
        self::assertStringContainsString('error', $readBuffer());
    }

    #[Test]
    public function subscriptions_maps_filesystem_notice_to_notice_handler(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $event = new ExceptionEvent(dbName: 'db', exception: new RuntimeException('file notice'));

        // When
        $subscriptions = $pretty->subscriptions();
        (self::callbackFor($subscriptions, Event::FilesystemNotice))(Event::FilesystemNotice, $event);

        // Then
        self::assertStringContainsString('notice', $readBuffer());
    }

    #[Test]
    public function subscriptions_maps_remaining_events_to_failure_handler(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $event = new ExceptionEvent(dbName: 'db', exception: new RuntimeException('connection lost'));

        $failureEvents = array_filter(
            Event::cases(),
            static fn(Event $e): bool => !in_array($e, [
                Event::MigrateSuccess,
                Event::MigrateError,
                Event::FilesystemNotice,
            ], true)
        );

        $subscriptions = $pretty->subscriptions();

        foreach ($failureEvents as $case) {
            [$climate2, $readBuffer2] = $this->makeBufferedClimate();
            $pretty2 = new PrettyConsoleOutput($climate2);
            $subscriptions2 = $pretty2->subscriptions();

            // When
            (self::callbackFor($subscriptions2, $case))($case, $event);

            // Then — failure() format contains "error:"
            $msg = "Event {$case->value} should use failure handler";
            self::assertStringContainsString('error', $readBuffer2(), $msg);
        }

        // Mark the outer $subscriptions usage to avoid risky test
        self::assertNotEmpty($subscriptions);
    }

    // -----------------------------------------------------------------------
    // success() — long format (up / down / repeatable)
    // -----------------------------------------------------------------------

    /** @param non-empty-string $action */
    #[Test]
    #[DataProvider('long_format_action_cases')]
    public function success_uses_long_format_for_migration_actions(string $action): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $context = new Context(
            dbName: 'testdb',
            filename: 'V5__migration.sql',
            query: 'SELECT 1',
            version: 5,
            dryRun: false,
        );
        $event = new MigrateSuccessEvent(action: $action, context: $context);

        // When
        $pretty->success(Event::MigrateSuccess, $event);

        // Then — long format includes "vers:" and the version number
        $output = $readBuffer();
        self::assertStringContainsString('vers:', $output);
        self::assertStringContainsString('5', $output);
        self::assertStringContainsString('testdb', $output);
        self::assertStringContainsString('V5__migration.sql', $output);
    }

    /** @return array<string, array{non-empty-string}> */
    public static function long_format_action_cases(): array
    {
        return [
            'happy path: up action uses long format' => ['up'],
            'happy path: down action uses long format' => ['down'],
            'happy path: repeatable action uses long format' => ['repeatable'],
        ];
    }

    #[Test]
    public function success_long_format_shows_done_when_not_dry_run(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $context = new Context(dbName: 'db', filename: 'V1.sql', query: 'SELECT 1', dryRun: false);
        $event = new MigrateSuccessEvent(action: 'up', context: $context);

        // When
        $pretty->success(Event::MigrateSuccess, $event);

        // Then
        self::assertStringContainsString('done', $readBuffer());
    }

    #[Test]
    public function success_long_format_shows_dry_run_label_when_dry_run_enabled(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $context = new Context(dbName: 'db', filename: 'V1.sql', query: 'SELECT 1', dryRun: true);
        $event = new MigrateSuccessEvent(action: 'up', context: $context);

        // When
        $pretty->success(Event::MigrateSuccess, $event);

        // Then
        self::assertStringContainsString('dry-run', $readBuffer());
    }

    // -----------------------------------------------------------------------
    // success() — short format (other actions)
    // -----------------------------------------------------------------------

    #[Test]
    public function success_uses_short_format_for_non_migration_actions(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $context = new Context(dbName: 'mydb', filename: 'fixture.sql', query: 'INSERT INTO x VALUES(1)');
        $event = new MigrateSuccessEvent(action: 'fixture', context: $context);

        // When
        $pretty->success(Event::MigrateSuccess, $event);

        // Then — short format does NOT contain "vers:"
        $output = $readBuffer();
        self::assertStringNotContainsString('vers:', $output);
        self::assertStringContainsString('mydb', $output);
        self::assertStringContainsString('fixture.sql', $output);
    }

    #[Test]
    public function success_short_format_shows_done_when_not_dry_run(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $context = new Context(dbName: 'db', filename: 'f.sql', query: 'SELECT 1', dryRun: false);
        $event = new MigrateSuccessEvent(action: 'create', context: $context);

        // When
        $pretty->success(Event::MigrateSuccess, $event);

        // Then
        self::assertStringContainsString('done', $readBuffer());
    }

    #[Test]
    public function success_short_format_shows_dry_run_label_when_dry_run_enabled(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $context = new Context(dbName: 'db', filename: 'f.sql', query: 'SELECT 1', dryRun: true);
        $event = new MigrateSuccessEvent(action: 'create', context: $context);

        // When
        $pretty->success(Event::MigrateSuccess, $event);

        // Then
        self::assertStringContainsString('dry-run', $readBuffer());
    }

    // -----------------------------------------------------------------------
    // error()
    // -----------------------------------------------------------------------

    #[Test]
    public function error_writes_error_line_and_exception_message_and_query(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $context = new Context(
            dbName: 'proddb',
            filename: 'V3__bad.sql',
            query: 'DROP DATABASE prod',
        );
        $exception = new RuntimeException('table does not exist');
        $event = new MigrateErrorEvent(action: 'up', context: $context, exception: $exception);

        // When
        $pretty->error(Event::MigrateError, $event);

        // Then
        $output = $readBuffer();
        self::assertStringContainsString('proddb', $output);
        self::assertStringContainsString('V3__bad.sql', $output);
        self::assertStringContainsString('table does not exist', $output);
        self::assertStringContainsString('DROP DATABASE prod', $output);
    }

    #[Test]
    public function error_output_contains_error_marker(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $context = new Context(dbName: 'db', filename: 'V1.sql', query: 'SELECT 1');
        $event = new MigrateErrorEvent(
            action: 'up',
            context: $context,
            exception: new RuntimeException('query failed'),
        );

        // When
        $pretty->error(Event::MigrateError, $event);

        // Then
        self::assertStringContainsString('error', $readBuffer());
    }

    // -----------------------------------------------------------------------
    // failure()
    // -----------------------------------------------------------------------

    #[Test]
    public function failure_writes_event_name_and_message(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $event = new ExceptionEvent(dbName: 'mydb', exception: new RuntimeException('connection refused'));

        // When
        $pretty->failure(Event::ConnectionError, $event);

        // Then
        $output = $readBuffer();
        self::assertStringContainsString($event->getName(), $output);
        self::assertStringContainsString($event->getMessage(), $output);
    }

    // -----------------------------------------------------------------------
    // notice()
    // -----------------------------------------------------------------------

    #[Test]
    public function notice_writes_event_name_and_message(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $event = new ExceptionEvent(dbName: 'filedb', exception: new RuntimeException('file already exists'));

        // When
        $pretty->notice(Event::FilesystemNotice, $event);

        // Then
        $output = $readBuffer();
        self::assertStringContainsString($event->getName(), $output);
        self::assertStringContainsString($event->getMessage(), $output);
    }

    #[Test]
    public function notice_output_contains_notice_keyword(): void
    {
        // Given
        [$climate, $readBuffer] = $this->makeBufferedClimate();
        $pretty = new PrettyConsoleOutput($climate);
        $event = new ExceptionEvent(dbName: 'db', exception: new RuntimeException('noticed something'));

        // When
        $pretty->notice(Event::FilesystemNotice, $event);

        // Then
        self::assertStringContainsString('notice', $readBuffer());
    }
}
