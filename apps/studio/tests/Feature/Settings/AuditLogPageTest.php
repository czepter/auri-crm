<?php

use App\Crm\Models\ClientCase;
use App\Enums\StaffRole;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

function auditEntries(array $query = []): array
{
    return test()->get('/einstellungen/protokoll?'.http_build_query($query))
        ->assertOk()
        ->viewData('page')['props']['entries'];
}

it('lists the entries newest first with a plain-text event, without the context', function () {
    $owner = staffMember(StaffRole::Admin);
    $project = ClientCase::factory()->create();

    AuditLogger::record('staff.logout', $owner);
    AuditLogger::record('project.booked', $owner, ['note' => 'context-marker'], $project->public_id, $project->id);

    $this->actingAs($owner, 'staff');
    $page = $this->get('/einstellungen/protokoll')->assertOk()->viewData('page')['props'];

    expect(array_column($page['entries'], 'label'))->toBe(['Projekt gebucht', 'Abgemeldet'])
        ->and($page['entries'][0]['actor'])->toBe($owner->name)
        ->and($page['entries'][0]['target'])->toBe(['name' => $project->name, 'href' => "/auftraege/{$project->public_id}"])
        ->and(json_encode($page['entries']))->not->toContain('context-marker');
});

it('hides the page from everybody but the owner', function () {
    $this->actingAs(staffMember(StaffRole::Manager), 'staff')
        ->get('/einstellungen/protokoll')
        ->assertNotFound();
});

it('filters by event, person and period', function () {
    $owner = staffMember(StaffRole::Admin);
    $other = staffMember(StaffRole::Manager);

    AuditLogger::record('staff.logout', $owner);
    AuditLogger::record('staff.logout', $other);
    AuditLogger::record('staff.login.succeeded', $other);
    $old = AuditLogger::record('staff.login.failed', $owner);
    DB::table('audit_logs')->where('id', $old->id)->update(['created_at' => now()->subDays(10)]);

    $this->actingAs($owner, 'staff');

    expect(array_column(auditEntries(['ereignis' => 'staff.logout']), 'label'))->toBe(['Abgemeldet', 'Abgemeldet'])
        ->and(auditEntries(['person' => $other->public_id, 'anmeldungen' => 1]))->toHaveCount(2)
        ->and(auditEntries(['person' => $owner->public_id, 'ereignis' => 'staff.logout']))->toHaveCount(1)
        ->and(auditEntries(['bis' => now()->subDays(5)->format('Y-m-d')]))->toHaveCount(1)
        ->and(auditEntries(['von' => now()->subDays(5)->format('Y-m-d'), 'anmeldungen' => 1]))->toHaveCount(3);
});

// A name that is missing here shows up raw in the log.
it('has a plain-text label for every event AuditLogger::record is called with', function () {
    $missing = [];
    $unread = [];

    foreach (File::allFiles(app_path()) as $file) {
        $source = $file->getContents();

        preg_match_all('/AuditLogger::record(?:Throttled)?\(/', $source, $calls, PREG_OFFSET_CAPTURE);

        foreach ($calls[0] as [$match, $offset]) {
            $depth = 0;
            $argument = '';

            for ($i = $offset + strlen($match); $i < strlen($source); $i++) {
                $char = $source[$i];
                $depth += match ($char) {
                    '(' => 1,
                    ')' => -1,
                    default => 0,
                };

                if ($depth < 0 || ($char === ',' && $depth === 0)) {
                    break;
                }

                $argument .= $char;
            }

            preg_match_all("/'([a-z0-9_.]+)'/", $argument, $names);

            if ($names[1] === []) {
                $unread[] = $file->getRelativePathname();
            }

            foreach ($names[1] as $name) {
                if (! trans()->has("audit.{$name}")) {
                    $missing[] = $name;
                }
            }
        }
    }

    expect($unread)->toBe([])->and($missing)->toBe([]);
});

it('shows old and new value of a settings change', function () {
    $owner = staffMember(StaffRole::Admin);

    AuditLogger::record('studio_settings.updated', $owner, ['key' => 'retention_months', 'old' => 24, 'new' => 36]);
    AuditLogger::record('studio_settings.updated', $owner, ['keys' => ['payments_stripe_mode', 'payments_stripe_test_secret_key'], 'changes' => ['payments_stripe_mode' => ['test', 'live']]]);

    $this->actingAs($owner, 'staff');

    expect(array_column(auditEntries(), 'detail'))->toBe([
        ['Stripe-Modus: Test → Live', 'Stripe Testschlüssel (geheim)'],
        ['Aufbewahrung (Monate): 24 → 36'],
    ]);
});

it('leaves portal link events out unless asked for, and names a legal text instead of its id', function () {
    $owner = staffMember(StaffRole::Admin);
    $template = App\Documents\Models\DocumentTemplate::factory()->create(['name' => 'Widerrufsbelehrung']);

    AuditLogger::record('portal.link_issued', null);
    AuditLogger::record('portal.link_consumed', null);
    AuditLogger::record('studio_settings.updated', $owner, [
        'key' => 'portal_legal_widerruf_family',
        'old' => null,
        'new' => $template->family_id,
    ]);

    $this->actingAs($owner, 'staff');
    $entries = auditEntries();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['detail'])->toBe(['Widerruf: — → Widerrufsbelehrung'])
        ->and(auditEntries(['ereignis' => 'portal.link_issued']))->toHaveCount(1);
});
