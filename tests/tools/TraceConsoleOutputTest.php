<?php

declare(strict_types=1);

namespace dbschemix\migrator\tests\tools;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use dbschemix\core\Context;
use dbschemix\core\event\Event;
use dbschemix\core\event\MigrateSuccessEvent;
use dbschemix\core\event\MigrateErrorEvent;
use dbschemix\core\event\Subscription;
use dbschemix\migrator\tests\Fakes\FakeConsoleOutput;
use dbschemix\migrator\tools\TraceConsoleOutput;
use RuntimeException;

#[CoversClass(TraceConsoleOutput::class)]
final class TraceConsoleOutputTest extends TestCase
{
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
        $output = new FakeConsoleOutput();
        $trace = new TraceConsoleOutput($output);

        // When
        $subscriptions = $trace->subscriptions();

        // Then — every Event case must have a subscription
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
        $output = new FakeConsoleOutput();
        $trace = new TraceConsoleOutput($output);

        // When
        $subscriptions = $trace->subscriptions();

        // Then — calling the MigrateSuccess callable writes the success message format (no "[name]" prefix)
        $context = new Context(dbName: 'testdb', filename: 'V1__init.sql', query: 'SELECT 1');
        $event = new MigrateSuccessEvent(action: 'up', context: $context);

        (self::callbackFor($subscriptions, Event::MigrateSuccess))(Event::MigrateSuccess, $event);

        self::assertCount(1, $output->lines);
        // success() writes event->getMessage() which is "[testdb] up: V1__init.sql, vers: 0"
        self::assertSame($event->getMessage(), $output->lines[0]);
    }

    #[Test]
    public function subscriptions_maps_non_success_events_to_error_handler(): void
    {
        // Given
        $context = new Context(dbName: 'testdb', filename: 'V1__init.sql', query: 'DROP TABLE x');
        $error = new MigrateErrorEvent(
            action: 'up',
            context: $context,
            exception: new RuntimeException('column missing'),
        );

        // Then — every non-MigrateSuccess event callable writes "[name] message" format
        foreach (Event::cases() as $case) {
            if ($case === Event::MigrateSuccess) {
                continue;
            }

            // When — a fresh fake per case keeps the lines isolated
            $output = new FakeConsoleOutput();
            $subscriptions = (new TraceConsoleOutput($output))->subscriptions();

            (self::callbackFor($subscriptions, $case))($case, $error);
            self::assertCount(1, $output->lines, "Expected one writeln for event {$case->value}");
            self::assertStringContainsString($error->getName(), $output->lines[0]);
            self::assertStringContainsString($error->getMessage(), $output->lines[0]);
        }
    }

    // -----------------------------------------------------------------------
    // success()
    // -----------------------------------------------------------------------

    #[Test]
    public function success_writes_event_message_via_writeln(): void
    {
        // Given
        $output = new FakeConsoleOutput();
        $trace = new TraceConsoleOutput($output);
        $context = new Context(dbName: 'mydb', filename: 'V2__add_users.sql', query: 'CREATE TABLE users (id INT)');
        $event = new MigrateSuccessEvent(action: 'up', context: $context);

        // When
        $trace->success(Event::MigrateSuccess, $event);

        // Then
        self::assertCount(1, $output->lines);
        self::assertSame($event->getMessage(), $output->lines[0]);
    }

    #[Test]
    public function success_message_includes_db_name_action_filename_and_version(): void
    {
        // Given
        $output = new FakeConsoleOutput();
        $trace = new TraceConsoleOutput($output);
        $context = new Context(dbName: 'analytics', filename: 'V10__schema.sql', query: 'SELECT 1', version: 10);
        $event = new MigrateSuccessEvent(action: 'down', context: $context);

        // When
        $trace->success(Event::MigrateSuccess, $event);

        // Then
        self::assertCount(1, $output->lines);
        self::assertStringContainsString('analytics', $output->lines[0]);
        self::assertStringContainsString('V10__schema.sql', $output->lines[0]);
        self::assertStringContainsString('10', $output->lines[0]);
    }

    // -----------------------------------------------------------------------
    // error()
    // -----------------------------------------------------------------------

    #[Test]
    public function error_writes_formatted_name_and_message_via_writeln(): void
    {
        // Given
        $output = new FakeConsoleOutput();
        $trace = new TraceConsoleOutput($output);
        $context = new Context(dbName: 'proddb', filename: 'V3__bad.sql', query: 'DROP DATABASE prod');
        $event = new MigrateErrorEvent(
            action: 'up',
            context: $context,
            exception: new RuntimeException('query failed'),
        );

        // When
        $trace->error(Event::MigrateError, $event);

        // Then
        self::assertCount(1, $output->lines);
        $expected = sprintf('[%s] %s', $event->getName(), $event->getMessage());
        self::assertSame($expected, $output->lines[0]);
    }

    #[Test]
    public function error_message_contains_db_name_in_brackets(): void
    {
        // Given
        $output = new FakeConsoleOutput();
        $trace = new TraceConsoleOutput($output);
        $context = new Context(dbName: 'staging', filename: 'V5__rollback.sql', query: 'ROLLBACK');
        $event = new MigrateErrorEvent(
            action: 'down',
            context: $context,
            exception: new RuntimeException('connection lost'),
        );

        // When
        $trace->error(Event::MigrateError, $event);

        // Then
        self::assertStringContainsString('[staging', $output->lines[0]);
    }
}
