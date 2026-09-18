<?php

namespace App\Console\Commands;

use App\Support\DatabaseSafety;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('db:check {--shell-export : Output shell-safe KEY=value lines for scripts (no password).}')]
#[Description('Show the resolved database connection details (never prints credentials)')]
class DatabaseCheckCommand extends Command
{
    public function handle(): int
    {
        $connection = (string) config('database.default');
        $database = DatabaseSafety::databaseName($connection);
        $host = (string) config("database.connections.{$connection}.host", '');
        $port = (string) config("database.connections.{$connection}.port", '');
        $username = (string) config("database.connections.{$connection}.username", '');
        $environment = (string) config('app.env');
        $isTest = DatabaseSafety::isTestDatabase($database);
        $isProtected = DatabaseSafety::isProtectedDatabase($database);

        if ($this->option('shell-export')) {
            foreach ([
                'DB_CHECK_ENV' => $environment,
                'DB_CHECK_CONNECTION' => $connection,
                'DB_CHECK_HOST' => $host,
                'DB_CHECK_PORT' => $port,
                'DB_CHECK_NAME' => (string) $database,
                'DB_CHECK_USER' => $username,
            ] as $key => $value) {
                $this->line($key."='".str_replace("'", "'\\''", $value)."'");
            }

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Environment', $environment);
        $this->components->twoColumnDetail('Connection', $connection);
        $this->components->twoColumnDetail('Database', (string) $database);
        $this->components->twoColumnDetail('Host', $host !== '' ? $host : '(not applicable)');
        $this->components->twoColumnDetail('Test database', $isTest ? 'yes' : 'no');
        $this->components->twoColumnDetail('Protected database', $isProtected ? 'yes — destructive commands blocked' : 'no');

        return self::SUCCESS;
    }
}
