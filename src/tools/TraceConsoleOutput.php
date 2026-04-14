<?php

declare(strict_types=1);

namespace dbschemix\migrator\tools;

use Override;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use dbschemix\core\event\Event;
use dbschemix\core\event\EventInterface;
use dbschemix\core\event\EventSubscriberInterface;
use dbschemix\core\event\MigrateSuccessEvent;

/**
 * @api
 */
final readonly class TraceConsoleOutput implements EventSubscriberInterface
{
    public function __construct(private ConsoleOutputInterface $output)
    {
    }

    #[Override]
    public function subscriptions(): array
    {
        $subscriptions = [];
        foreach (Event::cases() as $event) {
            $subscriptions[$event->value] = match ($event) {
                Event::MigrateSuccess => $this->success(...),
                default => $this->error(...),
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
        $this->stdout($event->getMessage());
    }

    /**
     * @noinspection PhpUnusedParameterInspection
     */
    public function error(Event $name, EventInterface $event): void
    {
        $this->stdout(
            sprintf(
                '[%s] %s',
                $event->getName(),
                $event->getMessage()
            )
        );
    }

    private function stdout(string $message): void
    {
        $this->output->writeln($message);
    }
}
