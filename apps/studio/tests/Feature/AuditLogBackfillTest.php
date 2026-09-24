<?php

use App\Crm\Models\ClientCase;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// The backfill predates the case rename and writes the old table and column.
function backfillBeforeCaseRename(): void
{
    $rename = require database_path('migrations/2026_09_23_110000_rename_projects_to_cases.php');
    $rename->down();
    (require database_path('migrations/2026_08_13_000200_backfill_audit_log_project_ids.php'))->up();
    $rename->up();
}

it('places a historical row that names its project in context', function () {
    $project = ClientCase::factory()->create();

    $id = DB::table('audit_logs')->insertGetId([
        'tenant_id' => config('tenancy.tenant_id'),
        'public_id' => 'al_backfill_one',
        'event' => 'quote.accepted',
        'context' => json_encode(['project' => $project->public_id]),
        'created_at' => now(),
    ]);

    backfillBeforeCaseRename();

    expect(AuditLog::find($id)->case_id)->toBe($project->id);
});

it('leaves a historical row with no project in context alone', function () {
    $id = DB::table('audit_logs')->insertGetId([
        'tenant_id' => config('tenancy.tenant_id'),
        'public_id' => 'al_backfill_two',
        'event' => 'staff.login.succeeded',
        'context' => json_encode(['method' => 'password']),
        'created_at' => now(),
    ]);

    backfillBeforeCaseRename();

    expect(AuditLog::find($id)->case_id)->toBeNull();
});
