<?php

namespace App\Migration\Console\Commands;

use App\Migration\Actions\CommitHistoricalImport;
use App\Migration\Actions\PlanHistoricalImport;
use Illuminate\Console\Command;

/**
 * X-01: bulk-imports historical contacts, projects and jobs from
 * a directory of CSV files, so a studio moving onto this app is not left
 * re-typing years of client history by hand.
 *
 * Defaults to a dry run. PlanHistoricalImport resolves and validates every
 * row without writing anything, and this command prints exactly what a
 * --commit run would create, and every row it cannot map with a reason —
 * X-01's acceptance criterion, word for word. --commit hands the identical
 * plan to CommitHistoricalImport instead of a second, separately-written
 * write path, so the report can never drift from what actually gets written.
 *
 * Input format — {path} is a directory holding up to three CSV files, any of
 * which may be omitted. Full column reference: docs/historical-import.md.
 *
 *   contacts.csv   ref,name,email,phone
 *   projects.csv   ref,name,type,primary_contact_ref
 *   jobs.csv       project_ref,booked_at,booked_via
 *
 * `ref` and `*_ref` columns are a legacy identifier that exists only to link
 * rows to each other within this one import run — never stored on the
 * model a row resolves to, and not read back on a later run.
 */
class ImportHistoricalDataCommand extends Command
{
    protected $signature = 'migration:import-historical-data
        {path : Directory containing contacts.csv, projects.csv, jobs.csv}
        {--commit : Write the import. Without this flag, only a report is printed.}';

    protected $description = 'Dry-runs or commits a historical import of contacts, projects and jobs from CSV files (X-01). See docs/historical-import.md for the file format.';

    public function handle(PlanHistoricalImport $plan, CommitHistoricalImport $commit): int
    {
        $path = rtrim((string) $this->argument('path'), '/');

        if (! is_dir($path)) {
            $this->error("No such directory: {$path}");

            return self::FAILURE;
        }

        $importPlan = $plan($path);

        $this->reportEntity('Contacts', $importPlan->contacts['toCreate'], $importPlan->contacts['errors'],
            fn (array $row) => $row['name'].($row['email'] ? " <{$row['email']}>" : ''),
            reused: count($importPlan->contacts['toReuse']),
        );
        $this->reportEntity('Projects', $importPlan->projects['toCreate'], $importPlan->projects['errors'],
            fn (array $row) => "{$row['name']} ({$row['type']->value})",
        );
        $this->reportEntity('Jobs', $importPlan->jobs['toCreate'], $importPlan->jobs['errors'],
            fn (array $row) => "project {$row['project_ref']}, booked {$row['booked_at']}",
        );

        if (! $this->option('commit')) {
            $this->newLine();
            $this->comment('Dry run — nothing written. Re-run with --commit to import.');

            return self::SUCCESS;
        }

        $summary = $commit($importPlan);

        $this->newLine();
        $this->info(sprintf(
            'Imported %d contact(s) (%d matched to an existing contact by email), %d project(s), %d job(s).',
            $summary['contacts'], $summary['contacts_reused'], $summary['projects'], $summary['jobs'],
        ));

        return self::SUCCESS;
    }

    /**
     * One entity's slice of the report: how many rows would be created (with
     * up to five samples), and every row that can't be mapped, with its
     * reason. Shared between all three entities rather than repeated three
     * times — the acceptance criterion is the same shape for each of them.
     */
    private function reportEntity(string $label, array $toCreate, array $errors, \Closure $describe, int $reused = 0): void
    {
        $this->newLine();
        $suffix = $reused > 0 ? " (+{$reused} matched to an existing contact)" : '';
        $this->line("<info>{$label}:</info> would create ".count($toCreate).$suffix);

        foreach (array_slice($toCreate, 0, 5) as $row) {
            $this->line('  - '.$describe($row));
        }

        if ($errors === []) {
            return;
        }

        $this->line('  <fg=red>cannot map '.count($errors).' row(s):</>');

        foreach ($errors as $error) {
            $ref = $error['ref'] ?? '—';
            $this->line("    row {$error['row']} (ref {$ref}): {$error['reason']}");
        }
    }
}
