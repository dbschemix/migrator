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
use dbschemix\core\event\Subscription;

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
            $subscriptions[] = match ($event) {
                Event::MigrateSuccess => new Subscription($event, $this->success(...)),
                Event::MigrateError => new Subscription($event, $this->error(...)),
                Event::FilesystemNotice => new Subscription($event, $this->notice(...)),
                default => new Subscription($event, $this->failure(...)),
            };
        }

        return $subscriptions;
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    public function success(Event $name, EventInterface $event): void
    {
        assert($event instanceof MigrateSuccessEvent);

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
    public function error(Event $name, EventInterface $event): void
    {
        assert($event instanceof MigrateErrorEvent);

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
