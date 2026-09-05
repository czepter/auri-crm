<?php

namespace App\Migration\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * X-03's rollback command: restores the database from a db:cutover-snapshot
 * dump. --clean --if-exists drops every object the dump covers before
 * recreating it, so a bad or partial cutover import is undone completely
 * rather than merged with whatever is already there. --no-owner/--no-privileges
 * drop the dump's ALTER OWNER/GRANT statements — this restores into the same
 * role that took the snapshot, and a role that doesn't exist on the target
 * (a genuinely different environment, not just this app's own state) would
 * otherwise fail the whole restore over a permission statement that never
 * mattered for the data.
 *
 * Confirms before running unless --force: this is a database rewind, and the
 * runbook's rollback step is exactly the moment that confirmation matters
 * most, not the moment to skip it.
 */
class CutoverRestoreCommand extends Command
{
    protected $signature = 'db:cutover-restore
        {path : Path to a snapshot created by db:cutover-snapshot}
        {--connection= : Database connection to restore into (defaults to the app default connection)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Restores the database from a db:cutover-snapshot dump — the rollback step of the cutover runbook (X-03).';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("No such snapshot file: {$path}");

            return self::FAILURE;
        }

        $connectionName = $this->option('connection') ?? config('database.default');
        $config = config("database.connections.{$connectionName}");

        if (($config['driver'] ?? null) !== 'pgsql') {
            $this->error("Connection '{$connectionName}' is not pgsql — db:cutover-restore only supports Postgres.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("This overwrites every table in '{$config['database']}' with the snapshot at {$path}. Continue?")) {
            $this->comment('Aborted — nothing restored.');

            return self::SUCCESS;
        }

        $result = Process::env(array_filter(['PGPASSWORD' => $config['password'] ?? null]))
            ->timeout(300)
            ->run([
                'pg_restore',
                '--clean',
                '--if-exists',
                '--no-owner',
                '--no-privileges',
                '-h', $config['host'],
                '-p', (string) $config['port'],
                '-U', $config['username'],
                '-d', $config['database'],
                $path,
            ]);

        if ($result->failed()) {
            $this->error('pg_restore failed: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $this->info("Restored {$config['database']} from {$path}.");

        return self::SUCCESS;
    }
}
