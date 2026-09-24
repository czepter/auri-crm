<?php

use App\Crm\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('re-keys lead audit rows from the lead id to the case id and leaves other events alone', function () {
    $migration = require database_path('migrations/2026_09_23_110200_address_leads_by_case.php');
    $lead = Lead::factory()->create();
    $migration->down();

    DB::table('leads')->where('id', $lead->id)->update(['public_id' => 'leadoldid000000000000001']);
    $audit = fn (string $event, string $publicId) => DB::table('audit_logs')->insertGetId([
        'tenant_id' => $lead->tenant_id,
        'public_id' => $publicId,
        'event' => $event,
        'subject' => 'leadoldid000000000000001',
    ]);
    $stage = $audit('lead.stage_changed', 'auditstage00000000000001');
    $other = $audit('contact.updated', 'auditother00000000000001');

    $migration->up();

    expect(DB::table('audit_logs')->where('id', $stage)->value('subject'))->toBe($lead->case->public_id)
        ->and(DB::table('audit_logs')->where('id', $other)->value('subject'))->toBe('leadoldid000000000000001')
        ->and(DB::table('leads')->where('id', $lead->id)->value('legacy_public_id'))->toBe('leadoldid000000000000001');
});
