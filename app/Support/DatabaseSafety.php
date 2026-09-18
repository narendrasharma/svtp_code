<?php

namespace App\Support;

use RuntimeException;

/**
 * Centralized database safety guard.
 *
 * Exists because `php artisan migrate:fresh --force` once wiped the active
 * development database. All destructive-database decisions must go through
 * this class — do not scatter ad-hoc checks around the codebase.
 */
class DatabaseSafety
{
    /**
     * Database names that must never be wiped or used by the test suite.
     *
     * @return array<int, string>
     */
    public static function protectedDatabaseNames(): array
    {
        return [
            'tour_canyon',
            'tour_canyon_new',
        ];
    }

    /**
     * Artisan commands that destroy data and therefore need guarding.
     *
     * Plain `migrate` is intentionally NOT in this list so normal
     * development and deployment migrations keep working.
     *
     * @return array<int, string>
     */
    public static function destructiveCommands(): array
    {
        return [
            'migrate:fresh',
            'migrate:refresh',
            'migrate:reset',
            'db:wipe',
        ];
    }

    /**
     * Resolve the database name for a connection without exposing credentials.
     */
    public static function databaseName(?string $connection = null): ?string
    {
        $connection = $connection ?: (string) config('database.default');

        $database = config("database.connections.{$connection}.database");

        return is_string($database) ? $database : null;
    }

    /**
     * Whether a database name is an isolated, disposable test database.
     */
    public static function isTestDatabase(?string $database): bool
    {
        if (! is_string($database) || $database === '') {
            return false;
        }

        if ($database === ':memory:') {
            return true;
        }

        return str_contains(strtolower($database), 'test');
    }

    /**
     * Whether a database name is a known active (non-test) database.
     */
    public static function isProtectedDatabase(?string $database): bool
    {
        if (! is_string($database) || $database === '') {
            return false;
        }

        $protected = array_map(
            static fn (string $name): string => strtolower($name),
            self::protectedDatabaseNames()
        );

        return in_array(strtolower($database), $protected, true);
    }

    /**
     * Abort the test run unless the testing environment uses an isolated DB.
     *
     * Called from Tests\TestCase on every test. Has no escape hatch on
     * purpose: automated tests must NEVER run against an active database.
     *
     * @throws RuntimeException
     */
    public static function assertSafeTestDatabase(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $connection = (string) config('database.default');
        $database = self::databaseName($connection);

        if (self::isTestDatabase($database)) {
            return;
        }

        throw new RuntimeException(
            'REFUSING TO RUN TESTS: testing environment is pointed at non-test database '
            ."[connection: {$connection}] [database: ".var_export($database, true).']. '
            .'Tests must use an isolated database (sqlite :memory: or a database with "test" in its name). '
            .'Check phpunit.xml <env> settings and never set DB_DATABASE to an active database when APP_ENV=testing.'
        );
    }

    /**
     * Whether a destructive Artisan command may run against the given DB.
     */
    public static function destructiveCommandAllowed(?string $database): bool
    {
        if (self::isTestDatabase($database)) {
            return true;
        }

        $optOut = strtolower((string) env('ALLOW_DESTRUCTIVE_DB_COMMANDS', ''));

        return in_array($optOut, ['1', 'true', 'yes'], true);
    }

    /**
     * Block destructive Artisan commands on active databases.
     *
     * @throws RuntimeException
     */
    public static function guardDestructiveCommand(string $command, ?string $connection = null): void
    {
        if (! in_array($command, self::destructiveCommands(), true)) {
            return;
        }

        $connection = $connection ?: (string) config('database.default');
        $database = self::databaseName($connection);

        if (self::destructiveCommandAllowed($database)) {
            return;
        }

        throw new RuntimeException(
            "REFUSING TO RUN {$command}: database [{$database}] on connection [{$connection}] is not an isolated test database. "
            .'Destructive migration commands are blocked to protect the active development database. '
            .'If you really intend to wipe this database, re-run with ALLOW_DESTRUCTIVE_DB_COMMANDS=1 in the environment '
            .'(and back it up first: bash scripts/backup-db.sh). Plain `php artisan migrate` is unaffected.'
        );
    }
}
