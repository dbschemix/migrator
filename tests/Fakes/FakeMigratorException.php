<?php

declare(strict_types=1);

namespace dbschemix\migrator\tests\Fakes;

use dbschemix\core\exception\MigratorException;

/**
 * Concrete subclass of the abstract MigratorException for use in tests.
 */
final class FakeMigratorException extends MigratorException
{
    public function __construct(string $message = 'fake migrator error')
    {
        parent::__construct($message);
    }
}
