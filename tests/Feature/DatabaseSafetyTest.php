<?php

namespace Tests\Feature;

use App\Support\DatabaseSafety;
use RuntimeException;
use Tests\TestCase;

/**
 * Guards the guard: proves the test suite runs on an isolated database
 * and aborts loudly if it is ever pointed at an active database.
 *
 * NOTE: none of the tests in this class use RefreshDatabase on purpose —
 * they assert configuration, not migrated data.
 */
class DatabaseSafetyTest extends TestCase
{
    public function test_tests_run_on_sqlite_memory_not_on_dev_database(): void
    {
        $this->assertSame('sqlite', (string) config('database.default'));
        $this->assertSame(':memory:', DatabaseSafety::databaseName());
        $this->assertNotSame('tour_canyon_new', DatabaseSafety::databaseName());
        $this->assertNotSame('tour_canyon', DatabaseSafety::databaseName());
    }

    public function test_guard_aborts_when_testing_points_at_tour_canyon_new(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', 'tour_canyon_new');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/REFUSING TO RUN TESTS/');

        DatabaseSafety::assertSafeTestDatabase();
    }

    public function test_guard_aborts_when_testing_points_at_tour_canyon(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', 'tour_canyon');

        $this->expectException(RuntimeException::class);

        DatabaseSafety::assertSafeTestDatabase();
    }

    public function test_guard_allows_dedicated_mysql_test_database(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', 'tour_canyon_test');

        // Must not throw.
        DatabaseSafety::assertSafeTestDatabase();

        $this->assertTrue(DatabaseSafety::isTestDatabase('tour_canyon_test'));
    }

    public function test_destructive_commands_are_blocked_on_active_databases(): void
    {
        foreach (['tour_canyon', 'tour_canyon_new', 'laravel', 'production_db'] as $database) {
            foreach (DatabaseSafety::destructiveCommands() as $command) {
                config()->set('database.default', 'mysql');
                config()->set('database.connections.mysql.database', $database);

                try {
                    DatabaseSafety::guardDestructiveCommand($command);
                    $this->fail("{$command} should be blocked on [{$database}]");
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('REFUSING TO RUN', $e->getMessage());
                }
            }
        }

        $this->assertContains('migrate:fresh', DatabaseSafety::destructiveCommands());
        $this->assertContains('migrate:refresh', DatabaseSafety::destructiveCommands());
        $this->assertContains('migrate:reset', DatabaseSafety::destructiveCommands());
        $this->assertContains('db:wipe', DatabaseSafety::destructiveCommands());
    }

    public function test_plain_migrate_is_not_classified_destructive(): void
    {
        $this->assertNotContains('migrate', DatabaseSafety::destructiveCommands());
        $this->assertNotContains('migrate:status', DatabaseSafety::destructiveCommands());
    }

    public function test_destructive_commands_are_allowed_on_isolated_test_databases(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        // Must not throw (this is what RefreshDatabase relies on).
        DatabaseSafety::guardDestructiveCommand('migrate:fresh');

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.database', 'tour_canyon_test');

        DatabaseSafety::guardDestructiveCommand('migrate:fresh');

        $this->assertTrue(true);
    }
}
