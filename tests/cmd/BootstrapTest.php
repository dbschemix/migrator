<?php

declare(strict_types=1);

namespace dbschemix\migrator\tests\cmd;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use dbschemix\migrator\cmd\Bootstrap;
use dbschemix\migrator\tests\Fakes\FakeMigrator;

#[CoversClass(Bootstrap::class)]
final class BootstrapTest extends TestCase
{
    #[Test]
    public function assert_migrator_returns_same_instance_when_migrator_interface(): void
    {
        // Given
        $migrator = new FakeMigrator();

        // When
        $result = Bootstrap::assertMigrator($migrator);

        // Then
        self::assertSame($migrator, $result);
    }

    #[Test]
    public function assert_migrator_throws_when_config_returned_null(): void
    {
        // Given / When / Then
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('return $migrator');

        Bootstrap::assertMigrator(null);
    }

    #[Test]
    public function assert_migrator_throws_when_config_returned_other_object(): void
    {
        // Given / When / Then
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MigratorInterface');

        Bootstrap::assertMigrator(new stdClass());
    }
}
