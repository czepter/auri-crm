<?php

namespace App\Migration\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * X-03's rollback point: a pg_dump of the whole database, taken immediately
 * before a cutover import runs in commit mode. docs/cutover-runbook.md calls
 * this right before gallery:import-legacy --mode=backfill; db:cutover-restore
 * is the other half of the pair and undoes exactly what this captures.
 *
 * Custom format (-Fc): it's what pg_restore --clean expects, and the only
 * pg_dump format that's both compressed and restorable table-by-table if a
 * rollback ever needs to be partial.
 */
class CutoverSnapshotCommand extends Command
{
    protected $signature = 'db:cutover-snapshot
        {--path= : Where to write the dump (defaults to storage/app/private/cutover/cutover-{timestamp}.dump)}
        {--connection= : Database connection to snapshot (defaults to the app default connection)}';

    protected $description = 'Takes a pg_dump snapshot of the database before a cutover import, for db:cutover-restore to roll back to (X-03).';

    public function handle(): int
    {
        $connectionName = $this->option('connection') ?? config('database.default');
        $config = config("database.connections.{$connectionName}");

        if (($config['driver'] ?? null) !== 'pgsql') {
            $this->error("Connection '{$connectionName}' is not pgsql — db:cutover-snapshot only supports Postgres.");

            return self::FAILURE;
        }

        $path = $this->option('path') ?? storage_path('app/private/cutover/cutover-'.now()->format('Y-m-d-His').'.dump');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), recursive: true);
        }

        $result = Process::env(array_filter(['PGPASSWORD' => $config['password'] ?? null]))
            ->timeout(300)
            ->run([
                'pg_dump',
                '-h', $config['host'],
                '-p', (string) $config['port'],
                '-U', $config['username'],
                '-Fc',
                '-f', $path,
                $config['database'],
            ]);

        if ($result->failed()) {
            $this->error('pg_dump failed: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $this->info("Snapshot written to {$path}");

        return self::SUCCESS;
    }
}
