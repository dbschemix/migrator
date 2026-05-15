<?php

declare(strict_types=1);

namespace dbschemix\migrator\tests\Fakes;

use dbschemix\core\InputOptions;
use dbschemix\core\MigratorInterface;
use Throwable;

/**
 * Hand-written fake that records calls and can be configured to throw.
 */
final class FakeMigrator implements MigratorInterface
{
    public ?InputOptions $lastUpOptions = null;
    public ?InputOptions $lastDownOptions = null;
    public ?InputOptions $lastRedoOptions = null;
    public ?InputOptions $lastVerifyOptions = null;
    public ?InputOptions $lastFixtureOptions = null;
    public ?InputOptions $lastCreateOptions = null;
    public int $initCallCount = 0;

    public ?Throwable $upException = null;
    public ?Throwable $downException = null;
    public ?Throwable $redoException = null;
    public ?Throwable $verifyException = null;
    public ?Throwable $fixtureException = null;
    public ?Throwable $createException = null;
    public ?Throwable $initException = null;

    public function init(): void
    {
        $this->initCallCount++;
        if ($this->initException instanceof Throwable) {
            throw $this->initException;
        }
    }

    public function create(InputOptions $args = new InputOptions()): void
    {
        $this->lastCreateOptions = $args;
        if ($this->createException instanceof Throwable) {
            throw $this->createException;
        }
    }

    public function up(InputOptions $args = new InputOptions()): void
    {
        $this->lastUpOptions = $args;
        if ($this->upException instanceof Throwable) {
            throw $this->upException;
        }
    }

    public function down(InputOptions $args = new InputOptions()): void
    {
        $this->lastDownOptions = $args;
        if ($this->downException instanceof Throwable) {
            throw $this->downException;
        }
    }

    public function fixture(InputOptions $args = new InputOptions()): void
    {
        $this->lastFixtureOptions = $args;
        if ($this->fixtureException instanceof Throwable) {
            throw $this->fixtureException;
        }
    }

    public function redo(InputOptions $args = new InputOptions()): void
    {
        $this->lastRedoOptions = $args;
        if ($this->redoException instanceof Throwable) {
            throw $this->redoException;
        }
    }

    public function verify(InputOptions $args = new InputOptions()): void
    {
        $this->lastVerifyOptions = $args;
        if ($this->verifyException instanceof Throwable) {
            throw $this->verifyException;
        }
    }
}
