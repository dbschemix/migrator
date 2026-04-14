<?php

declare(strict_types=1);

namespace dbschemix\migrator\tools;

use Override;
use League\CLImate\CLImate;
use dbschemix\core\event\Event;
use dbschemix\core\event\EventInterface;
use dbschemix\core\event\EventSubscriberInterface;
use dbschemix\core\event\MigrateErrorEvent;
use dbschemix\core\event\MigrateSuccessEvent;

/**
 * @api
 * @see https://climate.thephpleague.com/
 */
final readonly class PrettyConsoleOutput implements EventSubscriberInterface
{
    public function __construct(
        private CLImate $output = new CLImate(),
    ) {
    }

    #[Override]
    public function subscriptions(): array
    {
        $subscriptions = [];
        foreach (Event::cases() as $event) {
            $subscriptions[$event->value] = match ($event) {
                Event::MigrateSuccess => $this->success(...),
                Event::MigrateError => $this->error(...),
                Event::FilesystemNotice => $this->notice(...),
                default => $this->failure(...),
            };
        }

        /**
         * @var non-empty-array<string, callable(Event $name, EventInterface $event):void> $subscriptions
         * @phpstan-ignore varTag.nativeType
         */
        return $subscriptions;
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    public function success(Event $name, MigrateSuccessEvent $event): void
    {
        $this->output->out(
            match ($event->action) {
                "up",
                "down",
                "repeatable" => sprintf(
                    '[<bold>%s</bold>] %s: %s, vers: %d <green>%s</green>',
                    $event->context->dbName,
                    $event->action,
                    $event->context->filename,
                    $event->context->version,
                    $event->context->dryRun ? 'dry-run' : 'done',
                ),
                default => sprintf(
                    '[<bold>%s</bold>] %s: %s <green>%s</green>',
                    $event->context->dbName,
                    $event->action,
                    $event->context->filename,
                    $event->context->dryRun ? 'dry-run' : 'done',
                )
            }
        );
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    public function error(Event $name, MigrateErrorEvent $event): void
    {
        $this->output->out(
            sprintf(
                '[<bold>%s</bold>] %s: %s <red>error</red>',
                $event->context->dbName,
                $event->action,
                $event->context->filename,
            )
        );

        $this->output->red($event->exception->getMessage());
        $this->output->out($event->context->query);
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    public function failure(Event $name, EventInterface $event): void
    {
        $this->output->out(
            sprintf(
                '[<bold>%s</bold>] error: <red>%s</red>',
                $event->getName(),
                $event->getMessage()
            )
        );
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    public function notice(Event $name, EventInterface $event): void
    {
        $this->output->out(
            sprintf(
                '[<bold>%s</bold>] notice: %s',
                $event->getName(),
                $event->getMessage()
            )
        );
    }
}
