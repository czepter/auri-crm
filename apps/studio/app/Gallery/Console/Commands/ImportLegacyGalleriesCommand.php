<?php

namespace App\Gallery\Console\Commands;

use App\Gallery\Actions\ImportLegacyGallery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * X-02's entry point: a fixture file of legacy gallery records, imported
 * either by --mode=link (point at where they already live) or
 * --mode=backfill (copy assets in and publish a real version 1) — see
 * App\Gallery\Actions\ImportLegacyGallery for what each mode actually does.
 *
 * Fixture format is a JSON array of objects, one per legacy gallery:
 *   [{"project_public_id": "...", "shoot_public_id": null, "title": "...",
 *     "external_url": "..."}]              // link mode
 *   [{"project_public_id": "...", "title": "...", "assets": ["..."]}]  // backfill mode
 *
 * --dry-run reports what each record would do without writing anything.
 */
class ImportLegacyGalleriesCommand extends Command
{
    protected $signature = 'gallery:import-legacy
        {file : Path to a JSON fixture of legacy gallery records}
        {--mode=link : link or backfill}
        {--dry-run : Report what would happen without writing anything}';

    protected $description = 'Imports legacy galleries from a JSON fixture, either linking to their existing location or backfilling them as real galleries.';

    public function handle(ImportLegacyGallery $import): int
    {
        $mode = $this->option('mode');
        if (! in_array($mode, ['link', 'backfill'], true)) {
            $this->error("--mode must be 'link' or 'backfill', got '{$mode}'.");

            return self::FAILURE;
        }

        $path = $this->argument('file');
        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $records = json_decode(File::get($path), true);
        if (! is_array($records)) {
            $this->error("File does not contain a valid JSON array: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $tally = [];

        foreach ($records as $index => $record) {
            $result = $import($record, $mode, $dryRun);
            $tally[$result['status']] = ($tally[$result['status']] ?? 0) + 1;

            $this->line("[{$index}] {$result['status']}: {$result['message']}");
        }

        $summary = collect($tally)->map(fn ($count, $status) => "{$count} {$status}")->implode(', ');
        $this->info(($dryRun ? '[dry run] ' : '').($summary === '' ? 'No records.' : $summary));

        return self::SUCCESS;
    }
}
